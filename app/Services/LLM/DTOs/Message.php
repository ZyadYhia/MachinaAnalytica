<?php

namespace App\Services\LLM\DTOs;

class Message
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?array $toolCalls = null,
        public readonly ?array $toolResults = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'tool_results' => $this->toolResults,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'],
            content: $data['content'],
            toolCalls: $data['tool_calls'] ?? null,
            toolResults: $data['tool_results'] ?? null,
        );
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self('assistant', $content, $toolCalls);
    }

    public static function system(string $content): self
    {
        return new self('system', $content);
    }
}
