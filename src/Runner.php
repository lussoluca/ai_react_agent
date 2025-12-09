<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class Runner {

  use DispatchTrait;
  use LoadableAgentsTrait;

  public function __construct(
    private RunContext $runContext,
    private MessageBusInterface $bus,
  ) {
  }

  public function run(string $objective, string $agent_id, string $thread_id, bool $detached = FALSE): void {
    $run_context = $this->runContext->load($thread_id);

    // If this is a new run, add the system prompt to the chat history.
    if (count($run_context->getChatHistory()) === 0) {
      $agent = $this->loadAgentFromConfig($agent_id);
      $run_context->addToHistory(new ChatMessage('system', $agent->getSystemPrompt()));
    }

    $run_context->addToHistory(new ChatMessage('user', $objective));
    $run_context->setDetached($detached);
    $this->dispatch($agent_id, $run_context);
  }

}

