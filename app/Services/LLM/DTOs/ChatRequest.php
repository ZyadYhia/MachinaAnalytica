<?php

namespace App\Services\LLM\DTOs;

class ChatRequest
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $conversationId = null,
        public readonly ?array $messages = null,
        public readonly ?string $systemPrompt = null,
        public readonly ?array $tools = null,
        public readonly array $options = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'message' => $this->message,
            'conversation_id' => $this->conversationId,
            'messages' => $this->messages,
            'system_prompt' => $this->systemPrompt,
            'tools' => $this->tools,
            'options' => $this->options,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'],
            conversationId: $data['conversation_id'] ?? null,
            messages: $data['messages'] ?? null,
            systemPrompt: $data['system_prompt'] ?? null,
            tools: $data['tools'] ?? null,
            options: $data['options'] ?? [],
        );
    }
}
