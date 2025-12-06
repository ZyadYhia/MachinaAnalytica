<?php

namespace App\Jobs;

use App\Events\ChatCompleted;
use App\Events\ChatFailed;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\LLMManager;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUnifiedChatJob implements ShouldQueue
{
    use BusQueueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Conversation $conversation,
        public ChatRequest $chatRequest,
        public string $jobId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LLMManager $llmManager): void
    {
        try {
            Log::info('Processing unified chat job', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'conversation_id' => $this->conversation->id,
                'provider' => $this->user->llmIntegration?->active_integration,
            ]);

            // Process chat request
            $response = $llmManager->chat($this->user, $this->chatRequest);

            // Store assistant message
            $assistantMessage = $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response->content,
                'tool_calls' => $response->toolCalls,
                'metadata' => $response->metadata,
            ]);

            $this->conversation->updateLastMessageTimestamp();

            // Broadcast completion event
            broadcast(new ChatCompleted(
                userId: $this->user->id,
                conversationId: (string) $this->conversation->id,
                response: $response->toArray(),
                metrics: [
                    'job_id' => $this->jobId,
                    'provider' => $this->user->llmIntegration?->active_integration,
                    'model' => $this->user->llmIntegration?->active_model,
                ],
            ));

            Log::info('Unified chat job completed successfully', [
                'job_id' => $this->jobId,
                'conversation_id' => $this->conversation->id,
                'message_id' => $assistantMessage->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Unified chat job failed', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Broadcast failure event
            broadcast(new ChatFailed(
                userId: $this->user->id,
                conversationId: (string) $this->conversation->id,
                error: $e->getMessage(),
                context: [
                    'job_id' => $this->jobId,
                    'provider' => $this->user->llmIntegration?->active_integration,
                ],
            ));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Unified chat job failed permanently', [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage(),
        ]);

        broadcast(new ChatFailed(
            userId: $this->user->id,
            conversationId: (string) $this->conversation->id,
            error: $exception->getMessage(),
            context: [
                'job_id' => $this->jobId,
                'attempts' => $this->attempts(),
            ],
        ));
    }
}
