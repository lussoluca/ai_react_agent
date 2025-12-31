<?php

namespace Drupal\ai_react_agent\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\AiRunContext;
use Drupal\ai_react_agent\AiTaskManager;
use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\ai_react_agent\Tools\ToolOutput;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\runner\Observer\Observer;
use Drupal\runner\RunContext;
use Drupal\runner\Runner;
use Drupal\runner\Task\TaskOutput;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    private readonly Runner $runner,
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
    $stored_history = $this
      ->tempStore
      ->get('ai_assistant_threads')
      ->get(
        $thread_id
      ) ?? [];

    foreach ($stored_history as $message) {
      if ($message instanceof  ChatMessage) {
        $rows[] = [
          'role' => $message->getRole(),
          'message' => $message->getText(),
        ];
      }

      if ($message instanceof ToolsFunctionOutputInterface) {
        $rows[] = [
          'role' => $message->getToolId(),
          'message' => \json_encode($message->getArguments()),
        ];
      }

      if ($message instanceof ToolOutput) {
        $rows[] = [
          'role' => $message->role,
          'message' => \json_encode($message->content),
        ];
      }
    }

    return new RowsOfFields($rows);
  }

  #[CLI\Command(name: 'ai_react_agent')]
  #[CLI\Argument(name: 'objective', description: 'The objective for the AI agent to accomplish.')]
  #[CLI\Argument(name: 'thread_id', description: 'The thread ID for memory storage.')]
  public function aiReActAgent($objective, $thread_id): void {
    $run_context = new AiRunContext(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
      agentId: 'drupal_cms_agent',
      threadId: $thread_id,
      objective: $objective,
    );

    $this
      ->runner
      ->run(
        new AiTaskManager(),
        $run_context
          ->withLoadedHistory()
          ->withPrivilegedUserId(1)
          ->withObserver(
            new class extends Observer {

              public function onMessage(
                RunContext $context,
                TaskOutput $output,
              ): void {
                if ($output->type === 'tool') {
                  echo "\n";
                  echo "\033[36m" . $output->content . "\033[0m";
                  echo "\n";
                }

                if ($output->type === 'assistant') {
                  echo $output->content;
                }
              }

              public function onEnd(RunContext $context): void {
                echo "\n";
              }

            }
          )
      );
  }

}
