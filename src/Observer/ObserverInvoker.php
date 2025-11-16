<?php

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\RunContext;

/**
 * ObserverInvoker class for managing observer notifications.
 */
class ObserverInvoker {

  /**
   * Notify all agent observers about response token during streaming.
   *
   * @param \Drupal\ai_react_agent\RunContext $context
   *   The run context.
   * @param \Drupal\ai_react_agent\AgentInterface $agent
   *   The agent that generated the response.
   * @param PayloadInterface $payload
   *   The response token payload.
   *
   * @return void
   */
  public function agentOnResponse(
    RunContext $context,
    AgentInterface $agent,
    PayloadInterface $payload,
  ): void {
    foreach ($context->agentObservers() as $observer) {
      $observer->onResponse($agent, $payload, $context);
    }
  }

}
