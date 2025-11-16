<?php

namespace Drupal\ai_react_agent\Observer;

use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\RunContext;

/**
 * Base AgentObserver class for monitoring and responding to agent lifecycle
 * events.
 */
abstract class AgentObserver {

  /**
   * Called during streaming when a partial response token is received.
   *
   * @param \Drupal\ai_react_agent\AgentInterface $agent
   *   The agent that generated the response token.
   * @param \Drupal\ai_react_agent\Payload\PayloadInterface $payload
   *   The response payload.
   * @param \Drupal\ai_react_agent\RunContext $context
   *   The execution context.
   */
  public abstract function onResponse(
    AgentInterface $agent,
    PayloadInterface $payload,
    RunContext $context,
  ): void;

}
