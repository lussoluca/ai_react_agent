<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\runner\Task\TaskInterface;

interface AgentInterface {

  public function getId(): string;

  public function getRunContext(): AiRunContext;

  public function run(): ?TaskInterface;

  public function withRunContext(AiRunContext $run_context): AgentInterface;

  public function getSystemPrompt(): string;

  /**
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface[] $tool_calls
   *
   * @return void
   */
  public function executeTools(array $tool_calls): void;

}
