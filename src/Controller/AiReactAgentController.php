<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_react_agent\AgentTools;
use Drupal\ai_react_agent\Memory;
use Drupal\ai_react_agent\Model;
use Drupal\ai_react_agent\Payload;
use Drupal\ai_react_agent\Runner;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Returns responses for AI ReACT Agent routes.
 */
final class AiReactAgentController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'ai.provider')]
    private readonly AiProviderPluginManager $aiProvider,
    #[Autowire(service: 'plugin.manager.ai.function_calls')]
    private readonly FunctionCallPluginManager $functionCallPluginManager,
    protected readonly SharedTempStoreFactory $tempStore,
  ) {}

  /**
   * Builds the response.
   */
  public function __invoke(Request $request): EventStreamResponse {
    $query = $request->query->get('query');
    $thread_id = $request->query->get('thread_id');

    /** @var \Drupal\ai\Entity\AiPromptInterface $prompt */
        $prompt = AiPrompt::load('agent_prompt__cms');
//    $prompt = AiPrompt::load('agent_prompt__agent_prompt');

    $self = $this;

    $response = new EventStreamResponse();

    $response->setCallback(function() use ($query, $thread_id, $prompt, $self) {
      $agent = new AgentTools(
        model: new Model(
          provider: 'openai',
          modelName: 'gpt-4',
        ),
        aiProviderPluginManager: $self->aiProvider,
        functionCallPluginManager: $self->functionCallPluginManager,
        systemPrompt: $prompt,
        observer: function(Payload $payload) {
          if ($payload->content !== NULL) {
            yield new ServerEvent($payload->content, type: 'message');
          }
        },
        ender: function() {
          yield new ServerEvent('close', type: 'message');
        },
        tools: [],
        maxIterations: 5,
      );

      $memory = new Memory(
        memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
          ->createInstance('last_n', ['max_messages' => 10]),
        tempStore: $self->tempStore,
      );

      $runner = new Runner(
        memory: $memory,
      );

      $stream = $runner->run($query, $agent, $thread_id);
      foreach ($stream as $event) {
        if ($event instanceof ServerEvent) {
          yield $event;
        }
      }
    });

    return $response;
  }

}
