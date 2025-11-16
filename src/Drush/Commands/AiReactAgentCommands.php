<?php

namespace Drupal\ai_react_agent\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\Agent;
use Drupal\ai_react_agent\AgentTools;
use Drupal\ai_react_agent\Memory;
use Drupal\ai_react_agent\Model;
use Drupal\ai_react_agent\Runner;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * A Drush commandfile.
 */
final class AiReactAgentCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an AiReactAgentCommands object.
   */
  public function __construct(
    #[Autowire(service: 'ai.provider')]
    private readonly AiProviderPluginManager $aiProvider,
    #[Autowire(service: 'plugin.manager.ai.function_calls')]
    private readonly FunctionCallPluginManager $functionCallPluginManager,
    protected readonly SharedTempStoreFactory $tempStore,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'ai_history')]
  #[CLI\Argument(name: 'thread_id', description: 'The thread ID for memory storage.')]
  #[CLI\FieldLabels(labels: [
    'role' => 'Role',
    'message' => 'Message',
  ])]
  #[CLI\DefaultTableFields(fields: ['role', 'message'])]
  public function aiHistory(
    $thread_id,
    $options = ['format' => 'table'],
  ): RowsOfFields {
    $memory = new Memory(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
    );

    $history = $memory->load($thread_id);

    foreach ($history->getChatHistory() as $message) {
      $rows[] = [
        'role' => $message->getRole(),
        'message' => $message->getText(),
      ];
    }

    return new RowsOfFields($rows);
  }

  #[CLI\Command(name: 'ai_react_agent')]
  #[CLI\Argument(name: 'query', description: 'The query to process.')]
  #[CLI\Argument(name: 'thread_id', description: 'The thread ID for memory storage.')]
  public function aiReActAgent($query, $thread_id): void {
    /** @var \Drupal\ai\Entity\AiPromptInterface $prompt */
    $prompt = AiPrompt::load('agent_prompt__cms');
    //    $prompt = AiPrompt::load('agent_prompt__agent_prompt');

    $agent = new AgentTools(
      model: new Model(
        provider: 'openai',
        modelName: 'gpt-4',
      ),
      aiProviderPluginManager: $this->aiProvider,
      functionCallPluginManager: $this->functionCallPluginManager,
      systemPrompt: $prompt,
      observer: function($payload) { // Non-generator observer outputs directly
        echo (string) $payload; // payload implements __toString
      },
      ender: function() {
        echo "\n\n";
      },
      tools: [],
      maxIterations: 5,
    );

    $memory = new Memory(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
    );

    $runner = new Runner(
      memory: $memory,
    );

    $stream = $runner->run($query, $agent, $thread_id);
    // Must iterate to execute generator body even if we ignore yielded events.
    foreach ($stream as $event) {
      if ($event instanceof ServerEvent) {
        // ServerEvent yielded; no action needed for CLI output in current design.
        continue;
      }
    }
  }

  #[CLI\Command(name: 'ai_agent')]
  #[CLI\Argument(name: 'query', description: 'The query to process.')]
  #[CLI\Argument(name: 'thread_id', description: 'The thread ID for memory storage.')]
  public function aiAgent($query, $thread_id): void {
    /** @var \Drupal\ai\Entity\AiPromptInterface $prompt */
    $prompt = AiPrompt::load('agent_prompt__agent_prompt');

    $agent = new Agent(
      model: new Model(
        provider: 'openai',
        modelName: 'gpt-4',
      ),
      aiProviderPluginManager: $this->aiProvider,
      systemPrompt: $prompt,
      tools: [],
      maxIterations: 5,
    );

    $memory = new Memory(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
    );

    $runner = new Runner(
      memory: $memory,
    );

    $output = $runner->run($query, $agent, $thread_id);

    foreach ($output as $message) {
      // $message may be a StreamedChatMessage or ChatMessage with getText() or other accessors.
      if (method_exists($message, 'getText')) {
        echo $message->getText();
      } elseif (method_exists($message, '__toString')) {
        echo (string) $message;
      }
    }
    echo "\n";
  }

}
