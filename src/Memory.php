<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;

class Memory {

  private array $currentHistory;

  private ChatMessage $systemPrompt;

  private string $thread_id;

  public function __construct(
    private readonly AiShortTermMemoryInterface $memoryManager,
    protected readonly SharedTempStoreFactory $tempStore,
  ) {
    $this->currentHistory = $memoryManager->getChatHistory();
    $this->systemPrompt = new ChatMessage('system', '');
  }

  public function load(string $thread_id): Memory {
    $stored_history = $this->tempStore->get('ai_assistant_threads')->get($thread_id) ?? [];
    $this->currentHistory = array_merge($this->currentHistory, $stored_history);
    $this->thread_id = $thread_id;

    return $this;
  }

  public function save(): void {
    $this->tempStore->get('ai_assistant_threads')->set($this->thread_id, $this->currentHistory);
  }

  public function getChatHistory(): array {
    return array_map(function($input) {
      return match (get_class($input)) {
        ToolCall::class => $this->buildToolInput($input),
        ToolOutput::class => $this->buildToolOutput($input),
        default => $input,
      };
    }, $this->currentHistory);
  }

  /**
   * Convert a ToolCall to a ChatCompletion compatible message
   *
   * @param ToolCall $toolCall
   */
  protected function buildToolInput(ToolCall $toolCall): ChatMessage
  {
    $message = new ChatMessage('assistant');

    $tool = new ToolsFunctionOutput(
      input: new ToolsFunctionInput(
        name: $toolCall->tool,
      ),
      tool_id: $toolCall->id,
      arguments: [],
    );
    $message->setTools([$tool]);

    return $message;
  }

  /**
   * Convert a ToolOutput to a ChatCompletion compatible message
   *
   * @param ToolOutput $toolOutput
   * @return MessageInterface
   */
  protected function buildToolOutput(ToolOutput $toolOutput): ChatMessage
  {
    $message = new ChatMessage('tool', $toolOutput->content);
    $message->setToolsId($toolOutput->toolCallId);

    return $message;
  }

  public function addToHistory(ChatMessage|MessageInterface $message): void {
    $this->currentHistory[] = $message;

    if ($message instanceof MessageInterface) {
      $this->save();

      return;
    }

    if ($message->getRole() === 'system') {
      $this->systemPrompt = $message;
    }

    $this->memoryManager->process(
      thread_id: $this->thread_id,
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

  public function getLastMessage() {
    return end($this->currentHistory);
  }


}
