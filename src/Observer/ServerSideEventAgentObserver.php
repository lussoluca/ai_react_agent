<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AiRunContext;
use Drupal\runner\Observer\Observer;
use Drupal\runner\RunContext;
use Drupal\runner\Task\TaskOutput;
use Symfony\Component\HttpFoundation\ServerEvent;

class ServerSideEventAgentObserver extends Observer {

  public function onMessage(RunContext $context, TaskOutput $output): void {
    assert($context instanceof AiRunContext);

    \Fiber::suspend(new ServerEvent($output->content, $output->type));
  }

  public function onEnd(RunContext $context): void {
    \Fiber::suspend(new ServerEvent('close', 'close'));
  }

}
