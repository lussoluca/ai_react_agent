<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\OperationType\Chat\ChatMessage;

readonly class Runner {

  public function __construct(
    private Memory $memory,
  ) {
  }

  public function run(string $objective, AgentInterface $agent, string $thread_id): \Generator {
    $memory = $this->memory->load($thread_id);

    if (count($memory->getChatHistory()) === 0) {
      $system_prompt = $agent->getSystemPrompt();
      $memory->addToHistory(new ChatMessage('system', $system_prompt->getPrompt()));
    }

    $memory->addToHistory(new ChatMessage('user', $objective));

    // Return the agent's streaming generator.
    return $agent->withMemoryManager($memory)->run(TRUE);
  }

}
