<?php

namespace Drupal\ai_react_agent\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\AgentInterface;
use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\ai_react_agent\Observer\AgentObserver;
use Drupal\ai_react_agent\Payload\EndPayload;
use Drupal\ai_react_agent\Payload\ToolPayload;
use Drupal\ai_react_agent\Payload\ResponsePayload;
use Drupal\ai_react_agent\Payload\PayloadInterface;
use Drupal\ai_react_agent\RunContext;
use Drupal\ai_react_agent\Runner;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Drush commands for AI ReAct Agent module.
 */
final class AiReactAgentCommands extends DrushCommands {

  use AutowireTrait;
  use LoadableAgentsTrait;

  /**
   * Constructs an AiReactAgentCommands object.
   */
  public function __construct(
    #[Autowire(service: 'ai.provider')]
    private readonly AiProviderPluginManager $aiProvider,
    #[Autowire(service: 'plugin.manager.ai.function_calls')]
    private readonly FunctionCallPluginManager $functionCallPluginManager,
    protected readonly SharedTempStoreFactory $tempStore,
    private readonly MessageBusInterface $bus,
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
    $memory = new RunContext(
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
    $run_context = new RunContext(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
    );
    $run_context->withAgentObserver(
      new class extends AgentObserver {

        public function onResponse(
          AgentInterface $agent,
          PayloadInterface $payload,
          RunContext $context,
        ): void {
          if ($payload instanceof EndPayload) {
            echo "\n";
          }

          if ($payload instanceof ToolPayload) {
            echo "\n";
            echo "\033[36m" . 'Running tool: ' . $payload->getContent() . ' (' . $payload->arguments['prompt'] . ')' . "\033[0m";
            echo "\n";
          }

          if ($payload instanceof ResponsePayload) {
            echo $payload->getContent();
          }
        }

      }
    );
    $run_context->setPrivileged(TRUE);

    $runner = new Runner(
      runContext: $run_context,
      bus: $this->bus,
    );

    $runner->run($query, 'drupal_cms_agent', $thread_id);
  }

}
