<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Payload;

class ResponsePayload implements PayloadInterface {

  public function __construct(
    public ?string $content,
    public ?string $role = NULL,
    public int $choice = 0,
    public ?int $inputTokens = NULL,
    public ?int $outputTokens = NULL,
  ) {}

  public function getContent(): string {
    return $this->content ?? '';
  }

  public function __toString(): string {
    return $this->content ?? '';
  }

}
