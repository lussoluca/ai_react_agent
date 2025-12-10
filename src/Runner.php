<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Symfony\Component\Messenger\MessageBusInterface;

readonly class Runner {

  use DispatchTrait;
  use LoadableAgentsTrait;

  public function __construct(
    private RunContext $runContext,
    private MessageBusInterface $bus,
  ) {
  }

  public function run(string $objective, string $agent_id, string $thread_id, bool $detached = FALSE): void {
    $run_context = $this->runContext->load($thread_id);
    $run_context->setDetached($detached);
    $run_context->setObjective($objective);
    $this->dispatch($agent_id, $run_context);
  }

}
