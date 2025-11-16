<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\Messenger\RunAgentMessage;
use Drupal\ai_react_agent\Payload\EndPayload;
use Drupal\ai_react_agent\Tools\ToolOutput;
use Symfony\Component\Messenger\MessageBusInterface;

final class Agent implements AgentInterface {

  private RunContext $runContext;

  private int $currentIteration;

  public function __construct(
    private readonly Model $model,
    private readonly AiProviderPluginManager $aiProviderPluginManager,
    private readonly FunctionCallPluginManager $functionCallPluginManager,
    private readonly AiPromptInterface $systemPrompt,
    private readonly MessageBusInterface $bus,
    private readonly array $tools,
    private readonly int $maxIterations = 5,
  ) {
    $this->currentIteration = 0;
  }

  public function run(): void {
    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $this
      ->aiProviderPluginManager
      ->createInstance(
        $this->model->provider
      );

    // Build chat input.
    $input = new ChatInput($this->runContext->getChatHistory());
    $input->setStreamedOutput(TRUE);
    $functions = $this->getFunctions();
    if (count($functions) && count($functions['normalized'])) {
      $input->setChatTools(new ToolsInput($functions['normalized']));
    }

    // Send chat request to AI provider and handle streamed response.
    $response = $ai_provider->chat($input, $this->model->modelName);
    $streamed_response = new StreamedResponseWrapper(
      $response->getNormalized(),
      $this,
      $this->functionCallPluginManager,
      $this->runContext,
    );

    // Loop through streamed payloads. For each payload, invoke observers and
    // accumulate message content.
    $message = '';
    foreach ($streamed_response as $payload) {
      $this
        ->runContext
        ->observerInvoker()
        ->agentOnResponse($this->runContext, $this, $payload);

      if (isset($payload->content)) {
        $message .= $payload->content;
      }
    }

    // Add the accumulated message to history.
    if (!empty($message)) {
      $this->runContext->addToHistory(
        new ChatMessage(
          role: 'assistant',
          text: $message
        )
      );
    }

    // Check if another iteration is needed.
    if ($streamed_response->shouldHandleToolCallFlag()) {
      $this->currentIteration++;
      if ($this->currentIteration < $this->maxIterations) {
        // Continue running the agent for another iteration.
        $this->bus->dispatch(new RunAgentMessage($this));
      }
    }

    // Notify observers that the agent has finished. This is required, for
    // example, to close streaming connections.
    $this
      ->runContext
      ->observerInvoker()
      ->agentOnResponse($this->runContext, $this, new EndPayload());
  }

  public function withRunContext(RunContext $run_context): AgentInterface {
    $this->runContext = $run_context;

    return $this;
  }

  public function getSystemPrompt(): AiPromptInterface {
    return $this->systemPrompt;
  }

  public function getFunctions(): array {
    $functions = [];
    foreach ($this->tools as $function_call_name => $value) {
      if ($value) {
        /** @var \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $function_call */
        $function_call = $this
          ->functionCallPluginManager
          ->createInstance(
            $function_call_name
          );
        $functions['normalized'][$function_call->getFunctionName(
        )] = $function_call->normalize();
        $functions['object'][$function_call->getFunctionName(
        )] = $function_call;
      }
    }

    return $functions;
  }

  public function executeTools(array $tool_calls): void {
    foreach ($tool_calls as $tool_call) {
      $this->runContext->addToHistory($tool_call);

      /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $function */
      $function = $this
        ->functionCallPluginManager
        ->convertToolResponseToObject(
          $tool_call
        );
      $message = $this->executeTool($function);
      $this->runContext->addToHistory(
        new ToolOutput(
          content: $message,
          toolCallId: $tool_call->getToolId(),
        )
      );
    }
  }

  public function executeTool(
    ExecutableFunctionCallInterface $tool,
  ): string {
    $tool->execute();

    return $tool->getReadableOutput();
  }

}


