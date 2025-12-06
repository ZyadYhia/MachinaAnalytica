<?php

namespace App\Services\LLM\Contracts;

use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\ChatResponse;

interface LLMProviderInterface
{
    /**
     * Send a chat completion request to the LLM provider
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * Check if the provider is available and healthy
     */
    public function checkHealth(): bool;

    /**
     * Get list of available models from the provider
     */
    public function listModels(): array;

    /**
     * Get the provider name
     */
    public function getProviderName(): string;

    /**
     * Check if the provider supports streaming responses
     */
    public function supportsStreaming(): bool;

    /**
     * Check if the provider supports tool calls
     */
    public function supportsTools(): bool;
}
