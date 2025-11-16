A Drupal 11 module that implements an AI-powered agent using the ReAct (Reasoning and Acting) framework. The agent can interact with users, reason about their requests, and execute tools to accomplish tasks through an iterative process.

## Overview

This module provides an AI agent that combines Large Language Models (LLMs) with tool execution capabilities. The agent follows the ReAct pattern: it reasons about the user's request, decides which tools to use, executes them, and iterates until the task is complete.

## Architecture

### Core Components

#### 1. Agent (`Agent.php`)

The main agent class that orchestrates the ReAct loop:

- **Initialization**: Configured with a model, system prompt, available tools, and maximum iterations
- **Execution Flow**:
  1. Retrieves chat history from `RunContext`
  2. Sends messages to the AI provider with available tools
  3. Processes streamed responses via `StreamedResponseWrapper`
  4. Detects tool calls and executes them via `executeTools()`
  5. Adds tool outputs to chat history
  6. Iterates until completion or max iterations reached
  7. Notifies observers at each step

#### 2. RunContext (`RunContext.php`)

Manages the execution state and conversation history:

- **Chat History**: Stores and retrieves messages (user, assistant, tool calls, tool outputs)
- **Persistence**: Uses Drupal's SharedTempStore to maintain conversation threads
- **Memory Management**: Integrates with AI module's short-term memory plugins
- **Observer Pattern**: Manages `AgentObserver` instances for monitoring agent activity

#### 3. StreamedResponseWrapper (`StreamedResponseWrapper.php`)

Handles streaming responses from the AI provider:

- **Response Processing**: Iterates over streamed tokens from the AI model
- **Tool Detection**: Identifies when the AI wants to call tools by analyzing the response structure
- **Tool Call Capturing**: Accumulates streamed tool call data (function name and arguments)
- **Payload Generation**: Creates `ResponsePayload` and `ToolPayload` objects for observers
- **Tool Execution Trigger**: Signals the agent when tool execution is needed

#### 4. Runner (`Runner.php`)

Entry point for agent execution:

- Loads or initializes conversation thread
- Adds system prompt (first message only)
- Adds user's query to history
- Dispatches `RunAgentMessage` to Symfony Messenger for asynchronous execution

#### 5. Observer System

The observer pattern enables real-time monitoring and streaming:

- **`AgentObserver`**: Abstract base class for observers
- **`ObserverInvoker`**: Manages and invokes all registered observers
- **`ServerSideEventAgentObserver`**: Uses PHP Fibers to enable Server-Sent Events streaming

### Data Flow

```
User Input → Runner → RunAgentMessage → RunAgentHandler → Agent
                                                           ↓
                                         AI Provider ← ChatInput
                                                           ↓
                                         StreamedResponse → StreamedResponseWrapper
                                                           ↓
                                         Observers ← Payloads (ResponsePayload, ToolPayload)
                                                           ↓
                                         Tool Detection → executeTools()
                                                           ↓
                                         Tool Results → RunContext (history)
                                                           ↓
                                         Iterate or Complete → EndPayload
```

## Controllers

### AiReactAgentController (`src/Controller/AiReactAgentController.php`)

Provides a web endpoint for streaming agent responses using Server-Sent Events (SSE) and PHP Fibers.

#### How It Works

1. **Request Handling**:
   - Accepts query string parameters: `query` (user's question) and `thread_id` (conversation identifier)
   - Returns an `EventStreamResponse` for SSE streaming

2. **Fiber-Based Architecture**:
   ```php
   // Create fiber for agent execution
   $agent_fiber = new \Fiber(function () use ($runner, $query, $agent, $thread_id) {
     $runner->run($query, $agent, $thread_id);
   });
   ```
   
   - The agent execution runs inside a PHP Fiber (lightweight, cooperative multitasking)
   - The fiber can be suspended and resumed, enabling true streaming

3. **Streaming Process**:
   - **Start**: `$agent_fiber->start()` begins agent execution
   - **Suspend Points**: When `ServerSideEventAgentObserver` calls `\Fiber::suspend($event)`, control returns to the controller
   - **Resume**: Controller receives the suspended payload, yields it to the client, then resumes the fiber with `$agent_fiber->resume()`
   - **Loop**: Continues until `$agent_fiber->isTerminated()` is true

4. **Observer Configuration**:
   ```php
   $observer = new ServerSideEventAgentObserver();
   $run_context->withAgentObserver($observer);
   ```
   
   - `ServerSideEventAgentObserver` creates `ServerEvent` objects and suspends the fiber
   - This enables real-time streaming of:
     - AI response tokens (as they arrive)
     - Tool invocation notifications
     - Completion signals

5. **Benefits of Fiber Approach**:
   - **Zero Buffering**: Payloads sent to client immediately when generated
   - **Memory Efficient**: No need to store entire response before sending
   - **Responsive**: User sees progress in real-time
   - **Clean Code**: Synchronous-looking code that streams asynchronously

#### Example Request

```
GET /ai-react-agent/example?query=What+content+types+exist?&thread_id=abc123
```

The client receives SSE messages as the agent processes the request:
```
event: message
data: The system

event: tool
data: Running tool: content_type_agent_triage

event: message
data: has the following content types...

event: close
data: close
```

## Drush Commands

### AiReactAgentCommands (`src/Drush/Commands/AiReactAgentCommands.php`)

Provides command-line interface for agent interaction and history inspection.

#### How It Works

##### 1. `ai_react_agent` Command

Executes the agent from the command line with console output.

**Usage**:
```bash
drush ai_react_agent "What content types are available?" thread123
```

**Process**:

1. **User Context Setup**:
   ```php
   $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
   ```
   - Switches to admin user (UID 1) for proper permissions
   - Essential for tool execution that may require elevated permissions

2. **Agent Configuration**:
   ```php
   $agent = $this->loadAgentFromConfig();
   ```
   - Uses `LoadableAgentsTrait` to initialize the agent
   - Loads system prompt, configures tools, sets model parameters

3. **RunContext Setup**:
   ```php
   $run_context = new RunContext(
     memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
       ->createInstance('last_n', ['max_messages' => 10]),
     tempStore: $this->tempStore,
   );
   ```
   - Creates execution context with memory management
   - Limits history to last 10 messages for efficiency

4. **Console Observer**:
   ```php
   $run_context->withAgentObserver(
     new class extends AgentObserver {
       public function onResponse(
         AgentInterface $agent,
         Payload\PayloadInterface $payload,
         RunContext $context,
       ): void {
         if ($payload instanceof Payload\EndPayload) {
           echo "\n";
         }
         if ($payload instanceof Payload\ToolPayload) {
           echo "\n\033[36mInvoking tool: ".$payload->getContent()."\033[0m\n";
         }
         if ($payload instanceof Payload\ResponsePayload) {
           echo $payload->getContent();
         }
       }
     }
   );
   ```
   - Anonymous class extends `AgentObserver`
   - Provides real-time console output with colored tool invocations
   - Shows streaming response as it arrives from the AI

5. **Execution**:
   ```php
   $runner = new Runner(runContext: $run_context, bus: $this->bus);
   $runner->run($query, $agent, $thread_id);
   ```
   - Creates `Runner` and starts agent execution
   - Runs asynchronously via Symfony Messenger
   - Console observer provides immediate feedback

##### 2. `ai_history` Command

Displays conversation history for debugging and inspection.

**Usage**:
```bash
drush ai_history thread123 --format=table
```

**Process**:

1. **History Loading**:
   ```php
   $memory = new RunContext(
     memoryManager: \Drupal::service('plugin.manager.ai.short_term_memory')
       ->createInstance('last_n', ['max_messages' => 10]),
     tempStore: $this->tempStore,
   );
   $history = $memory->load($thread_id);
   ```
   - Creates `RunContext` to access stored history
   - Retrieves conversation from SharedTempStore

2. **Formatting**:
   ```php
   foreach ($history->getChatHistory() as $message) {
     $rows[] = [
       'role' => $message->getRole(),
       'message' => $message->getText(),
     ];
   }
   return new RowsOfFields($rows);
   ```
   - Converts chat history to table format
   - Shows role (user, assistant, tool, system) and message content
   - Supports multiple output formats (table, json, yaml, etc.)

**Output Example**:
```
 ------- ----------------------------------------- 
  Role    Message                                  
 ------- ----------------------------------------- 
  system  You are a helpful Drupal assistant...   
  user    What content types exist?                
  tool    [Function call to content_type_agent...] 
  tool    [Tool output: article, page, news...]    
  assistant  The system has: article, page, news...
 ------- ----------------------------------------- 
```

#### Key Features

- **Asynchronous Execution**: Uses Symfony Messenger for non-blocking agent runs
- **Real-Time Feedback**: Observer pattern provides streaming output
- **History Inspection**: Easy debugging of conversation flow
- **User Context Management**: Proper permission handling for tool execution

## Payload System

The module uses a payload system to represent different types of agent outputs:

- **`ResponsePayload`**: AI-generated text responses
- **`ToolPayload`**: Tool invocation notifications
- **`EndPayload`**: Signals completion of agent execution

All payloads implement `PayloadInterface`.

## Configuration

### Agent Configuration (`LoadableAgentsTrait`)

```php
return new Agent(
  model: new Model(
    provider: 'openai',
    modelName: 'gpt-4.1',
  ),
  systemPrompt: $prompt,  // Load from AiPrompt entity
  tools: [
    'ai_agents::ai_agent::content_type_agent_triage' => TRUE,
    'ai_agents::ai_agent::field_agent_triage' => TRUE,
    'ai_agents::ai_agent::taxonomy_agent_config' => TRUE,
  ],
  maxIterations: 10,
);
```

## Frontend Integration

The module provides a simple frontend interface (`js/frontend.js`) that:

1. Creates an input textbox and submit button
2. Connects to the SSE endpoint when the user submits a query
3. Streams responses in real-time
4. Displays tool invocations with colored indicators

Access via: `/ai-react-agent/frontend`

## Dependencies

- **ai:ai**: Core AI module for provider integration and tool management
- **sm:sm**: Symfony Messenger for asynchronous message handling

## Use Cases

- Question answering about Drupal site structure
- Content management automation
- Configuration assistance
- Interactive troubleshooting
- Custom workflow automation with tool chaining

## Technical Highlights

- **PHP Fibers**: Enables true streaming without blocking
- **Observer Pattern**: Extensible monitoring and output handling
- **Symfony Messenger**: Asynchronous, reliable agent execution
- **Memory Management**: Conversation persistence with configurable retention
- **Tool System**: Pluggable tool architecture via AI module's function calling system
