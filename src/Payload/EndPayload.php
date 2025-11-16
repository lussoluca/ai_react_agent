<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Payload;

readonly class EndPayload implements PayloadInterface {

  public function getContent(): string {
    return '';
  }

}
