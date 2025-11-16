<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

class ToolOutput implements MessageInterface {

  public readonly string $role;

  public function __construct(
    public readonly string $content,
    public readonly string $toolCallId,
  ) {
    $this->role = MessageInterface::ROLE_TOOL;
  }

  public function jsonSerialize(): array {
    return [
      'call_id' => $this->toolCallId,
      'output' => $this->content,
      'type' => 'function_call_output',
    ];
  }

}
