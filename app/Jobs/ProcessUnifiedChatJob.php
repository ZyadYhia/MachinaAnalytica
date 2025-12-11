<?php

namespace App\Jobs;

use App\Events\ChatCompleted;
use App\Events\ChatFailed;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\LLMManager;
use App\Services\McpToolService;
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
    public function handle(LLMManager $llmManager, McpToolService $mcpToolService): void
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

            Log::info('Initial chat response received', [
                'has_content' => ! empty($response->content),
                'has_tool_calls' => $response->hasToolCalls(),
                'requires_tool_execution' => $response->requiresToolExecution,
            ]);

            // Handle tool execution if required
            $maxIterations = 5;
            $iteration = 0;

            while ($response->requiresToolExecution && $iteration < $maxIterations) {
                $iteration++;

                Log::info('Tool execution required', [
                    'iteration' => $iteration,
                    'tool_calls' => count($response->toolCalls ?? []),
                ]);

                // Execute tools and get results
                $toolResults = $this->executeTools($response->toolCalls, $mcpToolService);

                // Store assistant message with tool calls
                $this->conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $response->content ?? '',
                    'tool_calls' => $response->toolCalls,
                    'tool_results' => $toolResults,
                    'metadata' => $response->metadata,
                ]);

                // Build messages with tool results
                $messages = $this->conversation->messages()
                    ->orderBy('created_at')
                    ->get()
                    ->map(function ($msg) {
                        $message = [
                            'role' => $msg->role,
                            'content' => $msg->content ?? '', // Ensure content is never null
                        ];

                        // Add tool_calls only for assistant messages that have them
                        if ($msg->role === 'assistant' && ! empty($msg->tool_calls)) {
                            $message['tool_calls'] = $msg->tool_calls;
                        }

                        return $message;
                    })
                    ->toArray();

                // Add tool result messages
                foreach ($toolResults as $toolResult) {
                    // Log the tool result structure for debugging
                    Log::info('Processing tool result for message', [
                        'tool_call_id' => $toolResult['tool_call_id'] ?? 'unknown',
                        'tool_name' => $toolResult['tool_name'] ?? 'unknown',
                        'result_keys' => array_keys($toolResult['result'] ?? []),
                        'result' => $toolResult['result'] ?? null,
                    ]);

                    // Determine the content to send
                    $content = '';
                    if (isset($toolResult['result'])) {
                        if (is_array($toolResult['result'])) {
                            if (isset($toolResult['result']['content'])) {
                                $content = is_string($toolResult['result']['content'])
                                    ? $toolResult['result']['content']
                                    : json_encode($toolResult['result']['content']);
                            } else {
                                // No 'content' key, encode the entire result
                                $content = json_encode($toolResult['result']);
                            }
                        } else {
                            // Result is not an array, convert to string
                            $content = (string) $toolResult['result'];
                        }
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolResult['tool_call_id'],
                        'content' => $content,
                    ];
                }

                // Continue conversation with tool results
                $newRequest = new ChatRequest(
                    message: '',
                    conversationId: $this->conversation->id,
                    messages: $messages,
                    systemPrompt: $this->chatRequest->systemPrompt,
                    options: $this->chatRequest->options,
                    tools: $this->chatRequest->tools,
                );

                $response = $llmManager->chat($this->user, $newRequest);
            }

            // Store final assistant message
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
                    'tool_iterations' => $iteration,
                ],
            ));

            Log::info('Unified chat job completed successfully', [
                'job_id' => $this->jobId,
                'conversation_id' => $this->conversation->id,
                'message_id' => $assistantMessage->id,
                'tool_iterations' => $iteration,
            ]);

            // Dispatch title generation job
            GenerateConversationTitleJob::dispatch($this->conversation, $this->user);
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
     * Execute MCP tools
     */
    protected function executeTools(array $toolCalls, McpToolService $mcpToolService): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            try {
                $toolName = $toolCall['function']['name'] ?? null;
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

                Log::info('Executing tool', [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ]);

                // Execute the tool via McpToolService
                $result = $mcpToolService->executeTool($toolName, $arguments);

                $results[] = [
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolName,
                    'result' => $result,
                ];

                Log::info('Tool executed successfully', [
                    'tool' => $toolName,
                    'result_size' => strlen(json_encode($result)),
                ]);
            } catch (\Exception $e) {
                Log::error('Tool execution failed', [
                    'tool' => $toolCall['function']['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolCall['function']['name'] ?? 'unknown',
                    'result' => ['error' => $e->getMessage()],
                ];
            }
        }

        return $results;
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
