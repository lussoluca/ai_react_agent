<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

/**
 * DTO to represent an AI model.
 */
final readonly class Model {

  public function __construct(
    public string $provider,
    public string $modelName,
  ) {
  }

}
