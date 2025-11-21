<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Controller;

use Drupal\ai\PluginManager\AiShortTermMemoryPluginManager;
use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\ai_react_agent\Observer\ServerSideEventAgentObserver;
use Drupal\ai_react_agent\RunContext;
use Drupal\ai_react_agent\Runner;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Returns responses for AI ReACT Agent routes.
 */
final class AiReactAgentController extends ControllerBase {

  use LoadableAgentsTrait;

  public function __construct(
    protected readonly SharedTempStoreFactory $tempStore,
    private readonly MessageBusInterface $bus,
    #[Autowire(service: 'plugin.manager.ai.short_term_memory')]
    private readonly AiShortTermMemoryPluginManager $aiShortTermMemory,
  ) {}

  /**
   * Builds the response using Fiber-based streaming.
   *
   * This controller uses PHP Fibers to enable true asynchronous streaming:
   * 1. Agent execution runs inside a Fiber
   * 2. When payloads are generated, StreamedAgentObserver calls Fiber::suspend()
   * 3. Control returns to this controller, which outputs the payload immediately
   * 4. The fiber is resumed to continue agent execution
   *
   * This approach allows payloads to be sent to the client as soon as they're
   * generated, rather than buffering them until the agent completes.
   */
  public function __invoke(Request $request): EventStreamResponse {
    $query = $request->query->get('query');
    $thread_id = $request->query->get('thread_id');

    return new EventStreamResponse(
      function () use ($query, $thread_id) {
        $runner = $this->getRunner();

        // Create fiber for agent execution.
        $agent_fiber = new \Fiber(function () use ($runner, $query, $thread_id) {
          $runner->run($query, '', $thread_id);
        });

        // Start the fiber.
        $payload = $agent_fiber->start();
        if ($payload !== null) {
          yield $payload;
        }

        // Process payloads as they become available.
        while (!$agent_fiber->isTerminated()) {
          $payload = $agent_fiber->resume();

          if ($payload !== null) {
            yield $payload;
          }
        }

        // Get any remaining output.
        $final_payload = $agent_fiber->getReturn();
        if ($final_payload !== null) {
          yield $final_payload;
        }
      },
    );
  }

  /**
   * @return \Drupal\ai_react_agent\Runner
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  function getRunner(): Runner {
    /** @var \Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface $memory_manager */
    $memory_manager = $this
      ->aiShortTermMemory
      ->createInstance('last_n', ['max_messages' => 10]);

    $run_context = new RunContext(
      memoryManager: $memory_manager,
      tempStore: $this->tempStore,
    );
    $observer = new ServerSideEventAgentObserver();
    $run_context->withAgentObserver($observer);

    return new Runner(
      runContext: $run_context,
      bus: $this->bus,
    );
  }

}
