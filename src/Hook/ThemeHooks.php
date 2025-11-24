<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class ThemeHooks {

  #[Hook('theme')]
  public function theme(): array {
    return ['deep_chat' => [
      'variables' => [],
    ]];
  }

}
