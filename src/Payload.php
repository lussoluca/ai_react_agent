<?php

namespace Drupal\ai_react_agent;

class Payload {

  public function __construct(
    public ?string $content,
    public ?string $role = NULL,
    public int $choice = 0,
    public ?int $inputTokens = NULL,
    public ?int $outputTokens = NULL,
  ) {}

  public function __toString(): string {
    return $this->content ?? '';
  }

}
