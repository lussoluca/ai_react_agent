<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

class StreamedResponseWrapper implements \IteratorAggregate {

  protected bool $isFinished = FALSE;

  protected array $generated = [];

  protected ?string $currentToolCallId = NULL;

  protected array $capturedToolCalls = [];

  public function __construct(
    private readonly StreamedChatMessageIteratorInterface $response,
    private readonly AgentInterface $agent,
  ) {}

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
        $this->handleToolCall();

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

  protected function shouldHandleToolCall(
    StreamedChatMessage $response,
  ): bool {
    return $response->getRaw()['choices'][0]['finish_reason'] === 'tool_calls'
      || $response->getRaw(
      )['choices'][0]['finish_reason'] === 'stop' && !empty($this->capturedToolCalls);
  }

  protected function handleToolCall(): void {
    if (empty($this->capturedToolCalls)) {
      return;
    }

    $toolCalls = [];
    foreach ($this->capturedToolCalls as $toolCallId => $toolCallData) {
      $toolCall = new ToolCall(
        tool: $toolCallData['name'],
        id: $toolCallId,
        argumentsPayload: $toolCallData['arguments'],
      );

      $toolCalls[] = $toolCall;
    }

    $this->agent->executeTools($toolCalls);
  }

  protected function getPayload(
    StreamedChatMessage $response,
  ): Payload {
    return new Payload(
      content: $response->getRaw()['choices'][0]['delta']['content'] ?? NULL,
      role: $response->getRaw()['choices'][0]['delta']['role'] ?? NULL,
      choice: $response->getRaw()['choices'][0]['index'] ?? 0,
      inputTokens: $response->getInputTokenUsage(),
      outputTokens: $response->getOutputTokenUsage(),
    );
  }

}
