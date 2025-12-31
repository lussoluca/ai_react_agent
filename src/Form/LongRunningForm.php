<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Form;

use Drupal\ai_react_agent\AiRunContext;
use Drupal\ai_react_agent\AiTaskManager;
use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\ai_react_agent\Observer\SimpleLoggerObserver;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\runner\Runner;

/**
 * Provides an AI ReACT Agent form.
 */
final class LongRunningForm extends FormBase {

  use AutowireTrait;
  use LoadableAgentsTrait;

  public function __construct(
    protected readonly SharedTempStoreFactory $tempStore,
    private readonly Runner $runner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_react_agent_long_running';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $thread_id = uniqid('thread_', TRUE);

    $run_context = new AiRunContext(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
      agentId: 'drupal_cms_agent',
      threadId: $thread_id,
      objective: $form_state->getValue('message'),
    );

    $task_manager = new AiTaskManager();

    $this
      ->runner
      ->run(
        $task_manager,
        $run_context
          ->detached()
          ->withPrivilegedUserId(1)
          ->withObserver(new SimpleLoggerObserver())
      );

    $this->messenger()->addStatus($this->t('The agent is started and running in the background. Thread ID: %thread_id', ['%thread_id' => $thread_id]));
  }

}
