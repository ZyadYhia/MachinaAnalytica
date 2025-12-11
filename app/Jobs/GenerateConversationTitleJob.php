<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\User;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\LLMManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateConversationTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Conversation $conversation,
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LLMManager $llmManager): void
    {
        try {
            Log::info("GenerateConversationTitleJob started for conversation {$this->conversation->id}", [
                'title' => $this->conversation->title,
                'user_id' => $this->user->id,
            ]);

            // Only generate title if it's the default one or explicitly requested
            // We can also check message count to avoid regenerating for long chats if not needed
            // For now, we'll check if title is "New Conversation"
            if ($this->conversation->title !== 'New Conversation') {
                Log::info("Skipping title generation: Title is not default ({$this->conversation->title})");

                return;
            }

            // Get first few messages for context
            $messages = $this->conversation->messages()
                ->orderBy('created_at')
                ->take(5)
                ->get()
                ->map(fn ($msg) => [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ])
                ->toArray();

            Log::info('Found '.count($messages).' messages for title generation', [
                'first_msg_snippet' => substr($messages[0]['content'] ?? '', 0, 100),
                'conversation_id' => $this->conversation->id,
            ]);

            if (empty($messages)) {
                Log::info('Skipping title generation: No messages found');

                return;
            }

            $systemPrompt = "You are a helpful assistant. Please summarize the request in the following conversation into a short, concise title of 3-6 words. Do not use quotes, punctuation, or words like 'Title:'. just return the text of the title.";

            // Use the user's active integration
            $integration = $this->user->llmIntegration;

            if (! $integration || $integration->active_integration === 'none') {
                Log::warning("Skipping title generation: No active integration for user {$this->user->id}");

                return;
            }

            $chatRequest = new ChatRequest(
                message: 'Please generate a title.', // Dummy message, the real context is in 'messages' array + system prompt acting on them
                conversationId: (string) $this->conversation->id,
                messages: $messages,
                systemPrompt: $systemPrompt,
                options: [
                    'model' => $integration->active_model,
                ]
            );

            // We use the same manager but we might want to ensure we don't trigger recursive tool calls if we had tools for this.
            // Title generation should be simple text.
            $response = $llmManager->chat($this->user, $chatRequest);

            $title = trim($response->content);

            // Basic cleanup if the model creates quotes
            $title = trim($title, '"\'');

            if (! empty($title)) {
                $this->conversation->update([
                    'title' => $title,
                ]);
                Log::info("Updated conversation {$this->conversation->id} title to: {$title}");

                broadcast(new \App\Events\ConversationUpdated($this->conversation));
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate conversation title: '.$e->getMessage());
        }
    }
}
