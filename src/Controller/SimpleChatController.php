<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for AI ReACT Agent routes.
 */
final class SimpleChatController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $build['content'] = [
      '#type' => 'item',
      '#attached' => [
        'library' => [
          'ai_react_agent/simple-chat',
        ],
      ],
    ];

    return $build;
  }

}
