<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JanApiResponding implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $conversationId,
        public int $iteration,
        public bool $hasToolCalls = false,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("jan-chat.{$this->userId}.{$this->conversationId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => 'jan_api_responding',
            'iteration' => $this->iteration,
            'has_tool_calls' => $this->hasToolCalls,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'jan.api.responding';
    }
}
