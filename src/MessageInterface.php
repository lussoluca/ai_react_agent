<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

interface MessageInterface {

  public const ROLE_SYSTEM = 'system';       // System instructions or context
  public const ROLE_DEVELOPER = 'developer'; // System instructions or context
  public const ROLE_ASSISTANT = 'assistant'; // AI-generated responses
  public const ROLE_USER = 'user';           // Human/user inputs
  public const ROLE_TOOL = 'tool';           // Tool execution results

}
