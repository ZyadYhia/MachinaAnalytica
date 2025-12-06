<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUnifiedChatJob;
use App\Models\Conversation;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\LLMManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnifiedChatController extends Controller
{
    public function __construct(
        protected LLMManager $llmManager
    ) {}

    /**
     * Send a chat message using the user's active provider
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|exists:conversations,id',
            'system_prompt' => 'nullable|string|max:5000',
        ]);

        $user = $request->user();
        $integration = $user->llmIntegration;

        if (! $integration || $integration->active_integration === 'none') {
            return response()->json([
                'error' => 'No active LLM integration configured. Please configure an integration in settings.',
            ], 400);
        }

        if ($integration->isOffline()) {
            return response()->json([
                'error' => 'The selected integration is currently offline. Please check your integration settings.',
            ], 503);
        }

        // Get or create conversation
        $conversation = $this->getOrCreateConversation(
            user: $user,
            conversationId: $validated['conversation_id'] ?? null,
            provider: $integration->active_integration,
            model: $integration->active_model
        );

        // Get conversation history as messages
        $messages = $conversation->messages()
            ->get()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'tool_calls' => $msg->tool_calls,
            ])
            ->toArray();

        // Build chat request
        $chatRequest = new ChatRequest(
            message: $validated['message'],
            conversationId: (string) $conversation->id,
            messages: $messages,
            systemPrompt: $validated['system_prompt'] ?? null,
            options: [
                'model' => $integration->active_model,
            ]
        );

        // Store user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);
        $conversation->updateLastMessageTimestamp();

        // Handle based on chat mode
        if ($integration->isAsyncMode()) {
            return $this->handleAsyncChat($user, $conversation, $chatRequest);
        }

        return $this->handleSyncChat($user, $conversation, $chatRequest);
    }

    /**
     * Handle synchronous chat request
     */
    protected function handleSyncChat($user, Conversation $conversation, ChatRequest $chatRequest): JsonResponse
    {
        try {
            $response = $this->llmManager->chat($user, $chatRequest);

            // Store assistant message
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response->content,
                'tool_calls' => $response->toolCalls,
                'metadata' => $response->metadata,
            ]);
            $conversation->updateLastMessageTimestamp();

            return response()->json([
                'success' => true,
                'message' => 'Chat completed successfully.',
                'conversation_id' => $conversation->id,
                'response' => $response->content,
                'metadata' => $response->metadata,
                'requires_tool_execution' => $response->requiresToolExecution,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process chat request: ' . $e->getMessage(),
                'conversation_id' => $conversation->id,
            ], 500);
        }
    }

    /**
     * Handle asynchronous chat request
     */
    protected function handleAsyncChat($user, Conversation $conversation, ChatRequest $chatRequest): JsonResponse
    {
        $jobId = Str::uuid()->toString();

        ProcessUnifiedChatJob::dispatch($user, $conversation, $chatRequest, $jobId);

        return response()->json([
            'success' => true,
            'message' => 'Chat request queued for processing.',
            'conversation_id' => $conversation->id,
            'job_id' => $jobId,
            'mode' => 'async',
        ]);
    }

    /**
     * Get or create a conversation
     */
    protected function getOrCreateConversation(
        $user,
        ?string $conversationId,
        string $provider,
        ?string $model
    ): Conversation {
        if ($conversationId) {
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return $conversation;
        }

        return Conversation::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'model' => $model,
            'title' => 'New Conversation',
        ]);
    }

    /**
     * List user's conversations
     */
    public function listConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('user_id', $user->id)
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Get a specific conversation with messages
     */
    public function getConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->with('messages')
            ->firstOrFail();

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation
     */
    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}
