<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Symfony\Component\HttpFoundation\ServerEvent;

final readonly class AgentTools implements AgentInterface {

  private Memory $memory;

  private int $currentIteration;

  public function __construct(
    private Model $model,
    private AiProviderPluginManager $aiProviderPluginManager,
    private FunctionCallPluginManager $functionCallPluginManager,
    private AiPromptInterface $systemPrompt,
    private \Closure $observer,
    private \Closure $ender,
    private array $tools,
    private int $maxIterations = 5,
  ) {}

  public function run(
    bool $streamed,
  ): \Generator { // Generator now
    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $this->aiProviderPluginManager->createInstance(
      $this->model->provider
    );
    $input = new ChatInput($this->memory->getChatHistory());

    if ($streamed) {
      $input->setStreamedOutput(TRUE);
    }

    $functions = $this->getFunctions();
    if (count($functions) && count($functions['normalized'])) {
      $input->setChatTools(new ToolsInput($functions['normalized']));
    }

    $response = $ai_provider->chat($input, $this->model->modelName);
    $streamed_response = new StreamedResponseWrapper(
      $response->getNormalized(),
      $this
    );

    $message = '';
    $last_payload = NULL;

    foreach ($streamed_response as $payload) {
      $observerResult = ($this->observer)($payload);
      // If observer returns a Generator, forward its yielded events.
      if ($observerResult instanceof \Generator) {
        foreach ($observerResult as $event) {
          yield $event;
        }
      }
      if (isset($payload->content)) {
        $message .= $payload->content;
      }
      $last_payload = $payload;
    }

    if (!empty($message) && isset($last_payload)) {
      $last_payload->content = $message;
      $this->memory->addToHistory(
        new ChatMessage(
          role: 'assistant',
          text: $last_payload->content
        )
      );
    }

    $enderResult = ($this->ender)();
    if ($enderResult instanceof \Generator) {
      foreach ($enderResult as $event) {
        yield $event;
      }
    }
  }

  public function withMemoryManager(Memory $memoryManager): AgentInterface {
    $this->memory = $memoryManager;

    return $this;
  }

  public function getSystemPrompt(): AiPromptInterface {
    return $this->systemPrompt;
  }

  public function getFunctions(): array {
    $function_definitions = ['ai_agents::ai_agent::content_type_agent_triage' => TRUE];

    $functions = [];
    foreach ($function_definitions as $function_call_name => $value) {
      if ($value) {
        /** @var \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $function_call */
        $function_call = $this->functionCallPluginManager->createInstance(
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
      $this->memory->addToHistory($tool_call);
      $this->memory->addToHistory(
        new ToolOutput(
          content: 'page, article',
          toolCallId: $tool_call->id,
        )
      );
    }

    // Re-run and forward any yielded events.
    $rerun = $this->run(TRUE);
    foreach ($rerun as $event) {
      // Side-effect: events yielded by tool execution are currently dropped here because
      // executeTools does not itself yield. Option B keeps executeTools void; controller
      // only sees initial run. If needed, refactor to collect these for later.
    }
  }

}


