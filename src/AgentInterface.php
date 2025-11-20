<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\Entity\AiPromptInterface;

interface AgentInterface {

  public function getId(): string;

  public function getRunContext(): RunContext;

  public function run(): void;

  public function withRunContext(RunContext $run_context): AgentInterface;

  public function getSystemPrompt(): AiPromptInterface;

  /**
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface[] $tool_calls
   *
   * @return void
   */
  public function executeTools(array $tool_calls): void;

}
