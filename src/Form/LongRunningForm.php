<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Form;

use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\ai_react_agent\Observer\SimpleLoggerObserver;
use Drupal\ai_react_agent\RunContext;
use Drupal\ai_react_agent\Runner;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provides an AI ReACT Agent form.
 */
final class LongRunningForm extends FormBase {

  use AutowireTrait;
  use LoadableAgentsTrait;

  public function __construct(
    protected readonly SharedTempStoreFactory $tempStore,
    private readonly MessageBusInterface $bus,
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
    $run_context = new RunContext(
      memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
        ->createInstance('last_n', ['max_messages' => 10]),
      tempStore: $this->tempStore,
    );

    $observer = new SimpleLoggerObserver();
    $run_context->withAgentObserver($observer);
    $run_context->setPrivileged(TRUE);

    $runner = new Runner(
      runContext: $run_context,
      bus: $this->bus,
    );

    $query = $form_state->getValue('message');
    // Generate a unique thread ID for this example.
    $thread_id = uniqid('thread_', TRUE);

    $runner->run($query, '', $thread_id, TRUE);

    $this->messenger()->addStatus($this->t('The message has been sent.'));
  }

}
