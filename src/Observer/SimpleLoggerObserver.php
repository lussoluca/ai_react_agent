<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\Payload\EndPayload;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\Payload\ResponsePayload;
use Drupal\ai_react_agent\Payload\ToolPayload;
use Drupal\ai_react_agent\RunContext;

class SimpleLoggerObserver extends AgentObserver {

  private string $accumulatedResponse;

  public function __construct() {
    $this->accumulatedResponse = '';
  }

  public function onResponse(
    AgentInterface $agent,
    PayloadInterface $payload,
    RunContext $context,
  ): void {
    if ($payload instanceof ToolPayload) {
      $this->accumulatedResponse .= '[Tool Invoked: '.$payload->getContent().']' . PHP_EOL;
    }

    if ($payload instanceof ResponsePayload) {
      $this->accumulatedResponse .= $payload->getContent();
    }

    if ($payload instanceof EndPayload) {
      \Drupal::logger('ai_react_agent')->info($this->accumulatedResponse);
      $this->accumulatedResponse = '';
    }
  }

}
