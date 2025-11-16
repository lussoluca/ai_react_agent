<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Swis\Agents\Exceptions\UnparsableToolCallException;

class ToolCall implements MessageInterface {

  public array $arguments = [];

  protected array $parameters = [];

  public function __construct(public string $tool, public string $id, public ?string $argumentsPayload = null)
  {
    $this->parameters = [
        'type' => 'function_call',
        'call_id' => $id,
        'name' => $tool,
        'arguments' => $argumentsPayload,
      ];

    $this->parseArgumentsPayload($argumentsPayload, $tool);
  }

  /**
   * @throws UnparsableToolCallException
   */
  protected function parseArgumentsPayload(?string $argumentsPayload, string $toolName): void
  {
    if ($argumentsPayload === null) {
      return;
    }

    // Parse the JSON arguments into an associative array for easier access
    $arguments = json_decode($argumentsPayload ?: '[]', true);

    if (! is_array($arguments)) {
      throw new UnparsableToolCallException(sprintf('The arguments for %s tool should be an object', $toolName));
    }

    $this->arguments = $arguments;
  }

}
