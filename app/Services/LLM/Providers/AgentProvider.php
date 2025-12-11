<?php

namespace App\Services\LLM\Providers;

use App\Services\Agent\AgentService;
use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\ChatResponse;
use Illuminate\Support\Facades\Log;

class AgentProvider implements LLMProviderInterface
{
    public function __construct(
        protected AgentService $agentService
    ) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        try {
            // Build messages array
            $messages = $request->messages ?? [];

            // Add system prompt if provided and not already present
            if ($request->systemPrompt && (empty($messages) || $messages[0]['role'] !== 'system')) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => $request->systemPrompt,
                ]);
            }

            // Add the new user message if provided
            if (! empty($request->message)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $request->message,
                ];
            }

            // Prepare payload
            $payload = [
                'messages' => $messages,
                'conversation_id' => $request->conversationId,
            ];

            // Call Agent Service
            $response = $this->agentService->chat($payload);

            // Log raw response for debugging
            Log::info('AgentProvider: Received response', [
                'has_markdown' => isset($response['markdown']),
                'is_tool' => $response['tool'] ?? false,
            ]);

            // DTO supports 'content' which is what we map 'markdown' to,
            // as 'markdown' contains the final text to show usage.
            // If it's a tool response, the agent returns 'markdown' with the analysis.
            $content = $response['markdown'] ?? ($response['message'] ?? '');

            // Map metadata
            $metadata = [
                'tool' => $response['tool'] ?? false,
                'intent' => $response['intent'] ?? null,
                'filters' => $response['filters'] ?? null,
                'data_count' => isset($response['data']) ? count($response['data']) : 0,
            ];

            // The Agent handles tools internally and returns the final result + data.
            // So from Laravel's perspective, no further tool execution is needed.
            return new ChatResponse(
                content: $content,
                conversationId: $request->conversationId,
                toolCalls: null, // Agent handled it
                metadata: $metadata,
                requiresToolExecution: false
            );

        } catch (\Exception $e) {
            Log::error('AgentProvider: Chat failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function checkHealth(): bool
    {
        return $this->agentService->checkHealth();
    }

    public function listModels(): array
    {
        // Agent abstracts models, return a default one
        return [
            [
                'id' => 'agent-default',
                'name' => 'Agent Default',
                'object' => 'model',
            ],
        ];
    }

    public function getProviderName(): string
    {
        return 'agent';
    }

    public function supportsStreaming(): bool
    {
        return false; // MVP: No streaming support from Agent yet
    }

    public function supportsTools(): bool
    {
        return true; // Agent supports tools internally
    }
}
