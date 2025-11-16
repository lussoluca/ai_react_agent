<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Payload;

interface PayloadInterface {

  public function getContent(): string;

}
