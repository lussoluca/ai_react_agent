<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_react_agent\Messenger\RunAgentMessage;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class Runner {

  public function __construct(
    private RunContext $runContext,
    private MessageBusInterface $bus,
  ) {
  }

  public function run(string $objective, AgentInterface $agent, string $thread_id): void {
    $run_context = $this->runContext->load($thread_id);

    if (count($run_context->getChatHistory()) === 0) {
      $system_prompt = $agent->getSystemPrompt();
      $run_context->addToHistory(new ChatMessage('system', $system_prompt->getPrompt()));
    }

    $run_context->addToHistory(new ChatMessage('user', $objective));

    $this->bus->dispatch(new RunAgentMessage($agent->withRunContext($run_context)));
  }

}
