<?php

namespace App\Services\LLM\Providers;

use App\Services\AnythingLLM\AnythingLLMService;
use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\ChatResponse;
use Illuminate\Support\Facades\Log;

class AnythingLLMProvider implements LLMProviderInterface
{
    protected string $defaultWorkspace;

    public function __construct(
        protected AnythingLLMService $anythingLLMService
    ) {
        $this->defaultWorkspace = config('services.anythingllm.default_workspace', 'default');
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        try {
            // Extract workspace from options or use default
            $workspace = $request->options['workspace'] ?? $this->defaultWorkspace;
            $threadSlug = $request->options['thread'] ?? null;

            // Make the request to AnythingLLM
            if ($threadSlug) {
                $response = $this->anythingLLMService->chatWithThread(
                    $workspace,
                    $threadSlug,
                    $request->message
                );
            } else {
                $response = $this->anythingLLMService->chat(
                    $workspace,
                    $request->message
                );
            }

            if (! $response->successful()) {
                throw new \Exception('AnythingLLM API request failed: ' . $response->body());
            }

            $responseData = $response->json();

            // Extract response content
            $content = $responseData['textResponse'] ?? '';

            // Extract metadata
            $metadata = [
                'id' => $responseData['id'] ?? null,
                'type' => $responseData['type'] ?? null,
                'close' => $responseData['close'] ?? null,
                'error' => $responseData['error'] ?? null,
            ];

            return new ChatResponse(
                content: $content,
                conversationId: $request->conversationId,
                toolCalls: null,
                metadata: $metadata,
                requiresToolExecution: false,
            );
        } catch (\Exception $e) {
            Log::error('AnythingLLM Provider Error: ' . $e->getMessage(), [
                'request' => $request->toArray(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function checkHealth(): bool
    {
        try {
            $response = $this->anythingLLMService->listWorkspaces();

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function listModels(): array
    {
        // AnythingLLM uses workspaces, not traditional models
        // Return list of workspaces as "models"
        try {
            $response = $this->anythingLLMService->listWorkspaces();

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return collect($data['workspaces'] ?? [])
                ->map(fn($workspace) => [
                    'id' => $workspace['slug'] ?? null,
                    'name' => $workspace['name'] ?? 'Unknown',
                    'object' => 'workspace',
                ])
                ->filter(fn($workspace) => $workspace['id'] !== null)
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('AnythingLLM listModels Error: ' . $e->getMessage());

            return [];
        }
    }

    public function getProviderName(): string
    {
        return 'anythingllm';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function supportsTools(): bool
    {
        return false;
    }
}
