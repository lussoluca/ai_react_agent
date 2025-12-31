<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Controller;

use Drupal\ai\PluginManager\AiShortTermMemoryPluginManager;
use Drupal\ai_react_agent\AiRunContext;
use Drupal\ai_react_agent\AiTaskManager;
use Drupal\ai_react_agent\Observer\ServerSideEventAgentObserver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\runner\Runner;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for AI ReACT Agent routes.
 */
final class AiReactAgentController extends ControllerBase {

  public function __construct(
    protected readonly SharedTempStoreFactory $tempStore,
    private readonly Runner $runner,
    #[Autowire(service: 'plugin.manager.ai.short_term_memory')]
    private readonly AiShortTermMemoryPluginManager $aiShortTermMemory,
  ) {}

  /**
   * Builds the response using Fiber-based streaming.
   *
   * This controller uses PHP Fibers to enable true asynchronous streaming:
   * 1. Agent execution runs inside a Fiber
   * 2. When payloads are generated, StreamedAgentObserver calls
   * Fiber::suspend()
   * 3. Control returns to this controller, which outputs the payload
   * immediately
   * 4. The fiber is resumed to continue agent execution
   *
   * This approach allows payloads to be sent to the client as soon as they're
   * generated, rather than buffering them until the agent completes.
   */
  public function __invoke(Request $request): EventStreamResponse {
    $objective = $request->query->get('objective');
    $agent_id = $request->query->get('agent_id');
    $thread_id = $request->query->get('thread_id');

    return new EventStreamResponse(
      function() use ($objective, $agent_id, $thread_id) {
        // Create fiber for agent execution.
        $agent_fiber = new \Fiber(
          function() use ($objective, $agent_id, $thread_id) {
            /** @var \Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface $memory_manager */
            $memory_manager = $this
              ->aiShortTermMemory
              ->createInstance('last_n', ['max_messages' => 10]);

            $run_context = new AiRunContext(
              memoryManager: $memory_manager,
              tempStore: $this->tempStore,
              agentId: $agent_id,
              threadId: $thread_id,
              objective: $objective,
            );
            $observer = new ServerSideEventAgentObserver();

            $task_manager = new AiTaskManager();

            $this
              ->runner
              ->run(
                $task_manager,
                $run_context
                  ->withLoadedHistory()
                  ->withObserver($observer)
              );
          }
        );

        // Start the fiber.
        $payload = $agent_fiber->start();
        if ($payload !== NULL) {
          yield $payload;
        }

        // Process payloads as they become available.
        while (!$agent_fiber->isTerminated()) {
          $payload = $agent_fiber->resume();

          if ($payload !== NULL) {
            yield $payload;
          }
        }

        // Get any remaining output.
        $final_payload = $agent_fiber->getReturn();
        if ($final_payload !== NULL) {
          yield $final_payload;
        }
      },
    );
  }

}
