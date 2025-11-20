<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\Payload\ResponsePayload;
use Drupal\ai_react_agent\Payload\ToolPayload;
use Drupal\ai_react_agent\RunContext;

class SimpleLoggerObserver extends AgentObserver {

  public function onResponse(
    AgentInterface $agent,
    PayloadInterface $payload,
    RunContext $context,
  ): void {
    if ($payload instanceof ToolPayload) {
      \Drupal::logger('ai_react_agent')->info('Invoking tool: '.$payload->getContent());
    }

    if ($payload instanceof ResponsePayload) {
      \Drupal::logger('ai_react_agent')->info($payload->getContent());
    }
  }

}
