<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\runner\RunContext;
use Drupal\runner\Task\TaskInterface;
use Drupal\runner\Task\TaskManager;

class AiTaskManager extends TaskManager {

  public function getStartTask(RunContext $run_context): ?TaskInterface {
    return new AgentTask($run_context);
  }

  public function getNextTask(RunContext $run_context): ?TaskInterface {
    return NULL;
  }

}
