<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Messenger;

use Drupal\ai_react_agent\AgentInterface;

class RunAgentMessage {

  public function __construct(
    public readonly AgentInterface $agent,
  ) {}

}
