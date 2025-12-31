<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\runner\Task\TaskInterface;

class AgentTask implements TaskInterface {

  use LoadableAgentsTrait;

  public function __construct(
    protected readonly AiRunContext $run_context,
  ) {}

  public function run(): ?TaskInterface {
    $agent = $this->loadAgentFromConfig($this->run_context->agentId);

    // If this is a new run, add the system prompt to the chat history.
    if (count($this->run_context->getChatHistory()) === 0) {
      $this->run_context->addToHistory(new ChatMessage('system', $agent->getSystemPrompt()));
    }

    $this->run_context->addToHistory(new ChatMessage('user', $this->run_context->objective));

    return $agent
      ->withRunContext($this->run_context)
      ->run();
  }

}
