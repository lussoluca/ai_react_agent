<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\runner\Observer\Observer;
use Drupal\runner\Task\TaskOutput;

class SimpleLoggerObserver extends Observer {

  private string $accumulatedResponse;

  public function __construct() {
    $this->accumulatedResponse = '';
  }

  public function onMessage(TaskOutput $output): void {
    $this->accumulatedResponse .= $output->content;
  }

  public function onEnd(): void {
    \Drupal::logger('ai_react_agent')->info($this->accumulatedResponse);
    $this->accumulatedResponse = '';
  }

}
