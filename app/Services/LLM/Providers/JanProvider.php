<?php

namespace App\Services\LLM\Providers;

use App\Services\Jan\JanService;
use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\ChatResponse;
use Illuminate\Support\Facades\Log;

class JanProvider implements LLMProviderInterface
{
    public function __construct(
        protected JanService $janService
    ) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        try {
            // Build messages array from conversation history or single message
            $messages = $request->messages ?? [];

            // Add system prompt if provided
            if ($request->systemPrompt && empty($messages)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $request->systemPrompt,
                ];
            }

            // Add the new user message
            if (! empty($request->message)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $request->message,
                ];
            }

            // Prepare data for Jan API
            $data = array_merge([
                'messages' => $messages,
                'stream' => false,
            ], $request->options);

            // Add tools if provided
            if ($request->tools) {
                $data['tools'] = $request->tools;
            }

            // Make the request to Jan
            $response = $this->janService->chatCompletion($data);

            if (! $response->successful()) {
                throw new \Exception('Jan API request failed: ' . $response->body());
            }

            $responseData = $response->json();

            // Extract response content
            $content = $responseData['choices'][0]['message']['content'] ?? '';
            $toolCalls = $responseData['choices'][0]['message']['tool_calls'] ?? null;

            // Extract metadata
            $metadata = [
                'model' => $responseData['model'] ?? null,
                'usage' => $responseData['usage'] ?? null,
                'id_slot' => $responseData['id_slot'] ?? null,
                'created' => $responseData['created'] ?? null,
            ];

            return new ChatResponse(
                content: $content,
                conversationId: $request->conversationId,
                toolCalls: $toolCalls,
                metadata: $metadata,
                requiresToolExecution: ! empty($toolCalls),
            );
        } catch (\Exception $e) {
            Log::error('Jan Provider Error: ' . $e->getMessage(), [
                'request' => $request->toArray(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function checkHealth(): bool
    {
        return $this->janService->checkConnection();
    }

    public function listModels(): array
    {
        try {
            $response = $this->janService->listModels();

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return collect($data['data'] ?? [])
                ->map(fn($model) => [
                    'id' => $model['id'] ?? null,
                    'name' => $model['id'] ?? $model['name'] ?? 'Unknown',
                    'object' => $model['object'] ?? 'model',
                ])
                ->filter(fn($model) => $model['id'] !== null)
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('Jan listModels Error: ' . $e->getMessage());

            return [];
        }
    }

    public function getProviderName(): string
    {
        return 'jan';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function supportsTools(): bool
    {
        return true;
    }
}
