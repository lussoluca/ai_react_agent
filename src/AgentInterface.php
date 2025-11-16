<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\Entity\AiPromptInterface;

interface AgentInterface {

  public function run(
    bool $streamed
  ): \Generator; // Changed return type to Generator for streaming Option B.

  public function withMemoryManager(Memory $memoryManager): AgentInterface;

  public function getSystemPrompt(): AiPromptInterface;

  public function executeTools(array $tool_calls): void;

}
