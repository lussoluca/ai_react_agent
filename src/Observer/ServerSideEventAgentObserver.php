<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\runner\Observer\Observer;
use Drupal\runner\Task\TaskOutput;
use Symfony\Component\HttpFoundation\ServerEvent;

class ServerSideEventAgentObserver extends Observer {

  public function onMessage(TaskOutput $output): void {
    \Fiber::suspend(new ServerEvent($output->content, $output->type));
  }

  public function onEnd(): void {
    \Fiber::suspend(new ServerEvent('close', 'close'));
  }

}
