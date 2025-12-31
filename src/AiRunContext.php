<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface;
use Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface;
use Drupal\ai_react_agent\Tools\MessageInterface;
use Drupal\ai_react_agent\Tools\ToolOutput;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\runner\RunContext;

class AiRunContext extends RunContext {

  use DependencySerializationTrait;

  private array $currentHistory;

  private ChatMessage $systemPrompt;

  public function __construct(
    public readonly AiShortTermMemoryInterface $memoryManager,
    public readonly SharedTempStoreFactory $tempStore,
    public readonly string $agentId,
    public readonly string $threadId,
    public readonly string $objective,
  ) {
    $this->currentHistory = $memoryManager->getChatHistory();
    $this->systemPrompt = new ChatMessage('system', '');
  }

  public function withLoadedHistory(): RunContext {
    $new = clone $this;

    $stored_history = $this
      ->tempStore
      ->get('ai_assistant_threads')
      ->get(
        $this->threadId
      ) ?? [];
    $new->currentHistory = array_merge($this->currentHistory, $stored_history);

    return $new;
  }

  public function addToHistory(
    ChatMessage | MessageInterface | ToolsFunctionOutputInterface $message,
  ): void {
    $this->currentHistory[] = $message;

    if ($message instanceof MessageInterface || $message instanceof ToolsFunctionOutputInterface) {
      $this->save();

      return;
    }

    if ($message->getRole() === 'system') {
      $this->systemPrompt = $message;
    }

    $this->memoryManager->process(
      thread_id: $this->threadId,
      consumer: 'ai_react_agent',
      chat_history: [],
      system_prompt: $this->systemPrompt->getText(),
      tools: [],
      original_chat_history: $this->currentHistory,
      original_system_prompt: $this->systemPrompt->getText(),
      original_tools: [],
    );

    $this->save();
  }

  public function getChatHistory(): array {
    return array_map(function($input) {
      return match (get_class($input)) {
        ToolsFunctionOutput::class => $this->buildToolInput($input),
        ToolOutput::class => $this->buildToolOutput($input),
        default => $input,
      };
    }, $this->currentHistory);
  }

  /**
   * Convert a ToolsFunctionOutput to a ChatCompletion compatible message.
   *
   * @param ToolsFunctionOutput $toolCall
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   */
  protected function buildToolInput(
    ToolsFunctionOutput $toolCall,
  ): ChatMessage {
    $message = new ChatMessage('assistant');
    $message->setTools([$toolCall]);

    return $message;
  }

  /**
   * Convert a ToolOutput to a ChatCompletion compatible message.
   *
   * @param \Drupal\ai_react_agent\Tools\ToolOutput $toolOutput
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   */
  protected function buildToolOutput(ToolOutput $toolOutput): ChatMessage {
    $message = new ChatMessage('tool', $toolOutput->content);
    $message->setToolsId($toolOutput->toolCallId);

    return $message;
  }

  public function save(): void {
    $this->tempStore->get('ai_assistant_threads')->set(
      $this->threadId,
      $this->currentHistory
    );
  }

}
