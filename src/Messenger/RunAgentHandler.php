<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Messenger;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_react_agent\LoadableAgentsTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RunAgentHandler {

  public function __construct(
    private readonly AccountSwitcherInterface $accountSwitcher,
  ) {}

  use LoadableAgentsTrait;

  public function __invoke(RunAgentMessage $message): void {
    $agent_id = $message->agent_id;
    $run_context = $message->runContext;

    // If this is a new run, add the system prompt to the chat history.
    if (count($run_context->getChatHistory()) === 0) {
      $agent = $this->loadAgentFromConfig($agent_id);
      $run_context->addToHistory(new ChatMessage('system', $agent->getSystemPrompt()));
    }

    $run_context->addToHistory(new ChatMessage('user', $run_context->getObjective()));

    if ($run_context->isPrivileged()) {
      // Switch to user 1 for privileged operations.
      $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    }

    $this->loadAgentFromConfig($agent_id)->withRunContext($run_context)->run();

    if ($run_context->isPrivileged()) {
      // Switch back to the original user.
      $this->accountSwitcher->switchBack();
    }
  }

}
