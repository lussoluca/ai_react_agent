<?php

declare(strict_types=1);

namespace Drupal\ai_react_agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Symfony\Component\Messenger\MessageBusInterface;

trait LoadableAgentsTrait {

  /**
   * Load an agent from configuration.
   *
   * This method will create an Agent instance based on ai_agent configuration.
   *
   * @return \Drupal\ai_react_agent\Agent
   */
  function loadAgentFromConfig(string $agent_id): Agent {
    /** @var \Drupal\ai\Entity\AiPromptInterface $prompt */
    $prompt = AiPrompt::load('agent_prompt__cms');
    //    $prompt = AiPrompt::load('agent_prompt__agent_prompt');

    return new Agent(
      model: new Model(
        provider: 'openai',
        modelName: 'gpt-4.1',
      ),
      aiProviderPluginManager: $this->getAiProvider(),
      functionCallPluginManager: $this->getFunctionCallPluginManager(),
      systemPrompt: $prompt,
      bus: $this->getBus(),
      tools: [
        'ai_agents::ai_agent::content_type_agent_triage' => TRUE,
        'ai_agents::ai_agent::field_agent_triage' => TRUE,
        'ai_agents::ai_agent::taxonomy_agent_config' => TRUE,
      ],
      maxIterations: 10,
    );
  }

  private function getAiProvider(): AiProviderPluginManager {
    return \Drupal::service('ai.provider');
  }

  private function getFunctionCallPluginManager(): FunctionCallPluginManager {
    return \Drupal::service('plugin.manager.ai.function_calls');
  }

  private function getBus(): MessageBusInterface {
    return \Drupal::service('sm.bus.default');
  }

}
