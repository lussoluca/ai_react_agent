<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

final readonly class Agent implements AgentInterface {

  private Memory $memory;

  private int $currentIteration;

  public function __construct(
    private Model $model,
    private AiProviderPluginManager $aiProviderPluginManager,
    private AiPromptInterface $systemPrompt,
    private array $tools,
    private int $maxIterations = 5,
  ) {}

  public function run(
    bool $streamed,
  ): \Generator { // Updated to generator
    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $this->aiProviderPluginManager->createInstance(
      $this->model->provider
    );
    $input = new ChatInput($this->memory->getChatHistory());

    if ($streamed) {
      $input->setStreamedOutput(TRUE);
    }

    $response = $ai_provider->chat($input, $this->model->modelName);
    $normalized = $response->getNormalized();

    // If streaming iterator, forward its internal iteration as ChatMessage objects.
    if ($normalized instanceof StreamedChatMessageIteratorInterface) {
      foreach ($normalized->doIterate() as $chunk) {
        yield $chunk; // chunks expected to be StreamedChatMessage
      }
      return; // end
    }

    // Single ChatMessage case.
    yield $normalized; // ChatMessage
  }

  public function withMemoryManager(Memory $memoryManager): AgentInterface {
    $this->memory = $memoryManager;

    return $this;
  }

  public function getSystemPrompt(): AiPromptInterface {
    return $this->systemPrompt;
  }

  public function executeTools(array $tool_calls): \Generator {
    return;
  }

}
