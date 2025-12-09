<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai_react_agent\Messenger\RunAgentMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

trait DispatchTrait {

  private readonly MessageBusInterface $bus;

  public function dispatch(string $agent_id, RunContext $run_context): void {
    $message = new RunAgentMessage($agent_id, $run_context);
    $envelope = new Envelope(
      message: $message,
      stamps: [
        new TransportNamesStamp($run_context->isDetached() ? 'asynchronous' : 'synchronous' ),
      ],
    );

    $this->bus->dispatch($envelope);
  }

}
