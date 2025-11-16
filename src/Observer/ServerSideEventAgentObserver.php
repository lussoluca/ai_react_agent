<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\Payload\EndPayload;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\Payload\ToolPayload;
use Drupal\ai_react_agent\RunContext;
use Symfony\Component\HttpFoundation\ServerEvent;

class ServerSideEventAgentObserver extends AgentObserver {

  public function onResponse(
    AgentInterface $agent,
    PayloadInterface $payload,
    RunContext $context,
  ): void {
    if ($payload instanceof EndPayload) {
      $event = new ServerEvent('close', 'close');
    }
    elseif ($payload instanceof ToolPayload) {
      $event = new ServerEvent('Running tool: ' . $payload->getContent(), 'tool');
    }
    else {
      $event = new ServerEvent($payload->getContent());
    }

    // Suspend the fiber and send the content immediately.
    \Fiber::suspend($event);
  }

}
