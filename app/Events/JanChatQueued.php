<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JanChatQueued implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $conversationId,
        public string $message,
        public array $context = [],
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
            'status' => 'queued',
            'message' => $this->message,
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'jan.chat.queued';
    }
}
