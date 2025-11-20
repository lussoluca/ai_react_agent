<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface;
use Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface;
use Drupal\ai_react_agent\Observer\AgentObserver;
use Drupal\ai_react_agent\Observer\ObserverInvoker;
use Drupal\ai_react_agent\Tools\MessageInterface;
use Drupal\ai_react_agent\Tools\ToolOutput;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\TempStore\SharedTempStoreFactory;

class RunContext {

  use DependencySerializationTrait;

  private array $currentHistory;

  private ChatMessage $systemPrompt;

  private string $thread_id;

  protected array $agentObservers = [];

  protected ObserverInvoker $observerInvoker;

  private bool $detached;

  private bool $privileged = false;

  public function __construct(
    private readonly AiShortTermMemoryInterface $memoryManager,
    protected readonly SharedTempStoreFactory $tempStore,
  ) {
    $this->currentHistory = $memoryManager->getChatHistory();
    $this->systemPrompt = new ChatMessage('system', '');
    $this->observerInvoker = new ObserverInvoker();
  }

  public function load(string $thread_id): RunContext {
    $stored_history = $this
      ->tempStore
      ->get('ai_assistant_threads')
      ->get(
        $thread_id
      ) ?? [];
    $this->currentHistory = array_merge($this->currentHistory, $stored_history);
    $this->thread_id = $thread_id;

    return $this;
  }

  public function save(): void {
    $this->tempStore->get('ai_assistant_threads')->set(
      $this->thread_id,
      $this->currentHistory
    );
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

  public function withAgentObserver(AgentObserver ...$observer): self {
    $this->agentObservers = array_merge($this->agentObservers, $observer);

    return $this;
  }

  public function agentObservers(): array {
    return $this->agentObservers;
  }

  public function observerInvoker(): ObserverInvoker {
    return $this->observerInvoker;
  }

  public function setDetached(bool $detached): void {
    $this->detached = $detached;
  }

  public function isDetached(): bool {
    return $this->detached;
  }

  public function setPrivileged(bool $privileged): void {
    $this->privileged = $privileged;
  }

  public function isPrivileged(): bool {
    return $this->privileged;
  }

}
