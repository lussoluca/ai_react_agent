<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\Payload\ResponsePayload;
use Drupal\ai_react_agent\Payload\ToolPayload;
use Drupal\Component\Serialization\Json;

class StreamedResponseWrapper implements \IteratorAggregate {

  protected bool $isFinished = FALSE;

  protected array $generated = [];

  protected ?string $currentToolCallId = NULL;

  protected array $capturedToolCalls = [];

  protected bool $shouldHandleToolCall;

  public function __construct(
    private readonly StreamedChatMessageIteratorInterface $response,
    private readonly AgentInterface $agent,
    private readonly FunctionCallPluginManager $functionCallPluginManager,
    private readonly RunContext $runContext,
  ) {
    $this->shouldHandleToolCall = FALSE;
  }

  public function getIterator(): \Generator {
    $generated = $this->generated;
    if (!$this->isFinished) {
      $generated = $this->response->doIterate();
    }

    foreach ($generated as $response) {
      $this->generated[] = $response;

      if (!$this->isApplicableResponse($response)) {
        continue;
      }

      if ($this->isToolCall($response)) {
        $this->captureToolCallStream($response);

        continue;
      }

      if ($this->shouldHandleToolCall($response)) {
        if (empty($this->capturedToolCalls)) {
          return;
        }

        $toolCalls = [];
        foreach ($this->capturedToolCalls as $id => $toolCallData) {
          $tool = $this
            ->functionCallPluginManager
            ->getFunctionCallFromFunctionName($toolCallData['name']);
          $this
            ->runContext
            ->observerInvoker()
            ->agentOnResponse(
              $this->runContext,
              $this->agent,
              new ToolPayload($tool->getPluginDefinition()['name']
              )
            );

          $toolCalls[] = $this->handleToolCall($id, $toolCallData);
        }

        $this->agent->executeTools($toolCalls);

        break;
      }

      yield $this->getPayload($response);
    }

    $this->isFinished = TRUE;
  }

  protected function isApplicableResponse(StreamedChatMessage $response): bool {
    return isset($response->getRaw()['choices'][0]['delta']);
  }

  protected function isToolCall(
    StreamedChatMessage $response,
  ): bool {
    return !empty(
      $response->getRaw()['choices'][0]['delta']['tool_calls']
      ) && isset(
        $response->getRaw()['choices'][0]['delta']['tool_calls'][0]['function']
      );
  }

  protected function captureToolCallStream(
    StreamedChatMessage $response,
  ): void {
    $toolCalls = $response->getRaw()['choices'][0]['delta']['tool_calls'];
    foreach ($toolCalls as $toolCall) {
      if (isset($toolCall['id'], $toolCall['function'], $toolCall['function']['name'])) {
        $this->currentToolCallId = $toolCall['id'];
        $this->capturedToolCalls[$this->currentToolCallId] = [
          'name' => $toolCall['function']['name'],
          'arguments' => '',
        ];
      }

      $this->capturedToolCalls[$this->currentToolCallId]['arguments'] .= $toolCall['function']['arguments'];
    }
  }

  public function shouldHandleToolCall(
    StreamedChatMessage $response,
  ): bool {
    $shouldHandleToolCall =  $response->getRaw()['choices'][0]['finish_reason'] === 'tool_calls'
      || $response->getRaw(
      )['choices'][0]['finish_reason'] === 'stop' && !empty($this->capturedToolCalls);

    $this->shouldHandleToolCall = $shouldHandleToolCall;

    return $shouldHandleToolCall;
  }

  public function shouldHandleToolCallFlag(): bool {
    return $this->shouldHandleToolCall;
  }

  protected function handleToolCall(?string $id, $toolCallData): ToolsFunctionOutput {
      $tool = $this
        ->functionCallPluginManager
        ->getFunctionCallFromFunctionName($toolCallData['name']);
      $tool->setToolsId($id ?? NULL);
      $input = $tool->normalize();

    return new ToolsFunctionOutput(
      input: $input,
      tool_id: $id,
      arguments: Json::decode($toolCallData['arguments']),
    );
  }

  protected function getPayload(
    StreamedChatMessage $response,
  ): ResponsePayload {
    return new ResponsePayload(
      content: $response->getRaw()['choices'][0]['delta']['content'] ?? NULL,
      role: $response->getRaw()['choices'][0]['delta']['role'] ?? NULL,
      choice: $response->getRaw()['choices'][0]['index'] ?? 0,
      inputTokens: $response->getInputTokenUsage(),
      outputTokens: $response->getOutputTokenUsage(),
    );
  }

}
