# AI React Agent Architecture Diagram

## Overview
This diagram illustrates the agent execution flow with streaming capabilities and observer pattern.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              ENTRY POINT                                    │
└─────────────────────────────────────────────────────────────────────────────┘

                                   Runner
                    ┌──────────────────────────────┐
                    │  - run(objective, agent_id)  │
                    │  - Uses: DispatchTrait       │
                    │  - Uses: LoadableAgentsTrait │
                    └──────────────┬───────────────┘
                                   │
                                   │ 1. Load thread
                                   │ 2. Add system prompt
                                   │ 3. Add user message
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           RUN CONTEXT                                       │
└─────────────────────────────────────────────────────────────────────────────┘

                               RunContext
            ┌────────────────────────────────────────────┐
            │  - Chat history (messages)                 │
            │  - Thread ID                               │
            │  - Agent Observers []                      │
            │  - ObserverInvoker                         │
            │  - Detached mode flag                      │
            │  - Privileged mode flag                    │
            │                                            │
            │  Methods:                                  │
            │  • load(thread_id)                         │
            │  • save()                                  │
            │  • getChatHistory()                        │
            │  • addToHistory(message)                   │
            │  • observerInvoker()                       │
            └────────────────┬───────────────────────────┘
                             │
                             │ Passed to dispatch
                             ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         MESSAGE BUS (Symfony)                               │
└─────────────────────────────────────────────────────────────────────────────┘

                            DispatchTrait
                    ┌────────────────────────┐
                    │  dispatch(agent_id,    │
                    │           run_context) │
                    └───────────┬────────────┘
                                │
                                │ Creates RunAgentMessage
                                │ Route: synchronous/asynchronous
                                ▼
                        RunAgentMessage
                    ┌────────────────────┐
                    │  - agent_id        │
                    │  - runContext      │
                    └────────┬───────────┘
                             │
                             │ Handled by
                             ▼
                      RunAgentHandler
                ┌─────────────────────────────┐
                │  1. Switch user if needed   │
                │  2. Load agent from config  │
                │  3. Call agent.run()        │
                └─────────────┬───────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            AGENT EXECUTION                                  │
└─────────────────────────────────────────────────────────────────────────────┘

                                Agent
            ┌──────────────────────────────────────────┐
            │  - Model config (provider, modelName)    │
            │  - System prompt                         │
            │  - Tools (function calls)                │
            │  - Max iterations                        │
            │  - Current iteration counter             │
            │                                          │
            │  run() {                                 │
            │    1. Get AI Provider                    │
            │    2. Build ChatInput                    │
            │    3. Enable streaming                   │
            │    4. Call AI provider.chat()            │
            │    5. Wrap in StreamedResponseWrapper    │
            │    6. Process stream                     │
            │    7. Check for tool calls               │
            │    8. Iterate if needed                  │
            │  }                                       │
            └────────────────┬─────────────────────────┘
                             │
                             │ Streams response
                             ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          STREAMING LAYER                                    │
└─────────────────────────────────────────────────────────────────────────────┘

                    StreamedResponseWrapper
        ┌────────────────────────────────────────────┐
        │  Wraps: StreamedChatMessageIterator        │
        │                                            │
        │  getIterator(): Generator {                │
        │    foreach (response chunk) {              │
        │      ┌──────────────────────────────┐      │
        │      │ 1. Check if applicable       │      │
        │      │ 2. Detect tool calls         │      │
        │      │ 3. Capture tool call data    │      │
        │      │ 4. Create payload            │      │
        │      └──────────────────────────────┘      │
        │                                            │
        │      ┌──────────────────────────────┐      │
        │      │ Notify Observers             │      │
        │      │ (via ObserverInvoker)        │      │
        │      └──────────────────────────────┘      │
        │                                            │
        │      yield payload                         │
        │    }                                       │
        │  }                                         │
        └────────────────┬───────────────────────────┘
                         │
                         │ For each chunk
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         OBSERVER PATTERN                                    │
└─────────────────────────────────────────────────────────────────────────────┘

                        ObserverInvoker
                ┌──────────────────────────────┐
                │  agentOnResponse(            │
                │    context,                  │
                │    agent,                    │
                │    payload                   │
                │  )                           │
                └────────────┬─────────────────┘
                             │
                             │ Notifies all observers
                             ▼
            ┌────────────────┴────────────────┐
            │                                 │
            ▼                                 ▼
    AgentObserver #1                 AgentObserver #2
┌──────────────────────┐        ┌──────────────────────┐
│ ServerSideEvent      │        │ SimpleLogger         │
│ AgentObserver        │        │ Observer             │
│                      │        │                      │
│ onResponse(agent,    │        │ onResponse(agent,    │
│   payload, context)  │        │   payload, context)  │
│                      │        │                      │
│ • Stream to client   │        │ • Log to file        │
│ • Send SSE events    │        │ • Debug output       │
└──────────────────────┘        └──────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                          PAYLOAD TYPES                                      │
└─────────────────────────────────────────────────────────────────────────────┘

    ResponsePayload              ToolPayload              EndPayload
┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│ content: string  │      │ content: string  │      │ (signals end)    │
│ (text chunk)     │      │ name: string     │      │                  │
└──────────────────┘      │ arguments: array │      └──────────────────┘
                          └──────────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                         EXECUTION FLOW SUMMARY                              │
└─────────────────────────────────────────────────────────────────────────────┘

1. Runner.run() → Initializes RunContext with chat history
2. DispatchTrait.dispatch() → Sends RunAgentMessage to message bus
3. RunAgentHandler → Receives message and loads agent
4. Agent.run() → Calls AI provider with streaming enabled
5. StreamedResponseWrapper → Processes streamed chunks
6. For each chunk:
   a. Create appropriate Payload (Response/Tool/End)
   b. ObserverInvoker.agentOnResponse() → Notifies all observers
   c. Observers handle payload (e.g., send SSE, log, etc.)
7. Accumulate message content and add to history
8. If tool calls detected → Execute tools → Iterate (up to max iterations)
9. Notify observers of completion


┌─────────────────────────────────────────────────────────────────────────────┐
│                            KEY FEATURES                                     │
└─────────────────────────────────────────────────────────────────────────────┘

✓ Streaming: Real-time token-by-token response delivery
✓ Observer Pattern: Multiple observers can monitor agent execution
✓ Tool Calling: Agents can call functions and iterate
✓ Persistence: Chat history saved in temp store
✓ Async/Sync: Supports both detached and synchronous execution
✓ Privileged Mode: Can escalate permissions when needed
✓ Iteration Control: Max iterations prevent infinite loops
```

## Component Descriptions

### Runner
- Entry point for agent execution
- Manages chat history initialization
- Adds system prompts and user messages
- Dispatches execution to message bus

### RunContext
- Central context object passed throughout execution
- Stores chat history and thread state
- Manages observer collection
- Provides observer invoker for notifications
- Handles persistence via temp store

### Agent
- Core execution logic
- Communicates with AI provider
- Enables streaming mode
- Handles tool calling and iteration
- Manages max iteration limits

### StreamedResponseWrapper
- Wraps AI provider's streamed response
- Processes chunks in real-time
- Detects and captures tool calls
- Creates appropriate payloads
- Triggers observer notifications

### ObserverInvoker
- Central notification hub
- Iterates through all registered observers
- Calls onResponse() for each observer
- Decouples agent from observer implementations

### AgentObserver
- Abstract base class for observers
- Implementations handle specific use cases:
  - ServerSideEventAgentObserver: Streams to browser
  - SimpleLoggerObserver: Logs execution details
  - Custom observers can be added

### Payloads
- ResponsePayload: Text content chunks
- ToolPayload: Tool call information
- EndPayload: Completion signal
- All implement PayloadInterface

## Threading Model

```
Synchronous Mode:
Runner → MessageBus(sync) → Handler → Agent → [blocks] → Response

Asynchronous Mode:
Runner → MessageBus(async) → [returns] ... Handler → Agent → Response
```

## Data Flow

```
User Input → Runner → RunContext → MessageBus → Agent → AI Provider
                ↓                                           ↓
         [Chat History]                            [Streaming Chunks]
                ↑                                           ↓
          Persistence ← RunContext ← Observers ← StreamedResponseWrapper
```

