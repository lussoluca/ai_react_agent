<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Messenger;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RunAgentHandler {

  public function __invoke(RunAgentMessage $message): void {
    $message->agent->run(TRUE);
  }

}
