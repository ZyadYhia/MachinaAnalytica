<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolsExecuting implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $conversationId,
        public array $toolCalls,
        public int $iteration,
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
            'status' => 'tools_executing',
            'tool_calls' => $this->toolCalls,
            'iteration' => $this->iteration,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'jan.tools.executing';
    }
}
