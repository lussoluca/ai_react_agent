<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\runner\Task\TaskInterface;

class ToolTask implements TaskInterface {

  use LoadableAgentsTrait;

  public function __construct(
    protected readonly AiRunContext $run_context,
  ) {}

  public function run(): ?TaskInterface {
    $agent = $this->loadAgentFromConfig($this->run_context->agentId);

    return $agent
      ->withRunContext($this->run_context)
      ->run();
  }

}
