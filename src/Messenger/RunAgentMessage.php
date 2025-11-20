<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Messenger;

use Drupal\ai_react_agent\RunContext;

readonly class RunAgentMessage {

  public function __construct(
    public string $agent_id,
    public RunContext $runContext,
  ) {}

}
