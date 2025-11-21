<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Payload;

readonly class ToolPayload implements PayloadInterface {

  public function __construct(
    public string $content,
    public string $name,
    public array $arguments,
  ) {}

  public function getContent(): string {
    return $this->content ?? '';
  }

}
