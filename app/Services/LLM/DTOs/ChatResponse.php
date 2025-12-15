<?php

namespace App\Services\LLM\DTOs;

class ChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $conversationId = null,
        public readonly ?array $toolCalls = null,
        public readonly ?array $metadata = null,
        public readonly bool $requiresToolExecution = false,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'content' => $this->content,
            'conversation_id' => $this->conversationId,
            'tool_calls' => $this->toolCalls,
            'metadata' => $this->metadata,
            'requires_tool_execution' => $this->requiresToolExecution,
        ], fn($value) => $value !== null && $value !== false);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? '',
            conversationId: $data['conversation_id'] ?? null,
            toolCalls: $data['tool_calls'] ?? null,
            metadata: $data['metadata'] ?? null,
            requiresToolExecution: $data['requires_tool_execution'] ?? false,
        );
    }

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }
}
