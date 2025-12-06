<?php

namespace App\Jobs;

use App\Events\ChatCompleted;
use App\Events\ChatFailed;
use App\Events\JanApiResponding;
use App\Events\JanChatQueued;
use App\Events\ToolsCompleted;
use App\Events\ToolsExecuting;
use App\Services\McpToolExecutor;
use App\Services\McpToolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessJanChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public int $backoff = 60;

    protected int $maxIterations = 5;

    public function __construct(
        public int $userId,
        public string $conversationId,
        public array $payload,
        public array $conversationHistory = [],
        public ?string $systemPrompt = null,
        public string $message = '',
    ) {}

    public function handle(McpToolService $toolService, McpToolExecutor $toolExecutor): void
    {
        $startTime = now();

        try {
            // Broadcast queued event
            event(new JanChatQueued(
                $this->userId,
                $this->conversationId,
                'Processing your message...',
                ['iteration' => 0]
            ));

            // Fetch available MCP tools
            $tools = [];
            try {
                $tools = $toolService->getAllTools();
                Log::info('ProcessJanChatJob: Fetched MCP tools', [
                    'user_id' => $this->userId,
                    'conversation_id' => $this->conversationId,
                    'count' => count($tools),
                ]);
            } catch (\Exception $e) {
                Log::warning('ProcessJanChatJob: Failed to fetch tools', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Build messages array
            $messages = $this->conversationHistory;

            // Add system prompt if provided
            if ($this->systemPrompt && (empty($messages) || $messages[0]['role'] !== 'system')) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ]);
            } elseif (empty($messages) || $messages[0]['role'] !== 'system') {
                // Add default system prompt with strict tool usage rules
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => 'You are a helpful AI assistant. IMPORTANT RULES: 1) Call each tool ONLY ONCE per conversation. 2) After receiving tool results, IMMEDIATELY analyze and present them - do NOT call tools again. 3) If you already have tool results, answer based on that data. 4) Never repeat tool calls. 5) Always end with a direct text response.',
                ]);
            }

            // Add user message
            $messages[] = [
                'role' => 'user',
                'content' => $this->message,
            ];

            // Prepare payload
            $payload = $this->payload;
            $payload['messages'] = $messages;

            if (! empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto';
                $payload['parallel_tool_calls'] = false; // Prevent confusion from parallel calls
            }

            $iteration = 0;
            $previousToolCalls = [];

            // Iterative tool execution loop
            while ($iteration < $this->maxIterations) {
                $iteration++;

                Log::info("ProcessJanChatJob: Iteration {$iteration}", [
                    'user_id' => $this->userId,
                    'conversation_id' => $this->conversationId,
                ]);

                // Broadcast API responding event
                event(new JanApiResponding(
                    $this->userId,
                    $this->conversationId,
                    $iteration,
                    false
                ));

                // Send to Jan API
                try {
                    $janResponse = $this->sendToJanApi($payload);
                } catch (\Exception $e) {
                    Log::error('ProcessJanChatJob: Jan API failed', [
                        'iteration' => $iteration,
                        'error' => $e->getMessage(),
                    ]);

                    event(new ChatFailed(
                        $this->userId,
                        $this->conversationId,
                        $e->getMessage(),
                        ['iteration' => $iteration]
                    ));

                    throw $e;
                }

                // Extract tool calls
                $toolCalls = ! empty($tools) ? $this->extractToolCalls($janResponse) : [];

                if (empty($toolCalls)) {
                    // No more tool calls - final response
                    Log::info('ProcessJanChatJob: Final response received', [
                        'user_id' => $this->userId,
                        'conversation_id' => $this->conversationId,
                    ]);

                    // Add assistant message to conversation
                    $assistantMessage = $janResponse['choices'][0]['message'] ?? null;
                    if ($assistantMessage) {
                        $messages[] = $assistantMessage;
                    }

                    // Save to session
                    session(["jan_conversations.{$this->conversationId}" => $messages]);

                    // Calculate metrics
                    $metrics = [
                        'iterations' => $iteration,
                        'duration_seconds' => now()->diffInSeconds($startTime),
                        'message_count' => count($messages),
                    ];

                    // Broadcast completion
                    event(new ChatCompleted(
                        $this->userId,
                        $this->conversationId,
                        [
                            'data' => $janResponse,
                            'conversation_id' => $this->conversationId,
                            'history_length' => count($messages),
                        ],
                        $metrics
                    ));

                    return;
                }

                // Broadcast tool execution event
                event(new ToolsExecuting(
                    $this->userId,
                    $this->conversationId,
                    $toolCalls,
                    $iteration
                ));

                // Check for repeated tool calls - use both name-based and exact matching
                $currentToolNames = array_map(function ($call) {
                    return $call['function']['name'] ?? 'unknown';
                }, $toolCalls);
                sort($currentToolNames);
                $toolNamesSignature = implode(',', $currentToolNames);

                $currentToolSignature = json_encode(array_map(function ($call) {
                    return [
                        'name' => $call['function']['name'] ?? 'unknown',
                        'args' => json_decode($call['function']['arguments'] ?? '{}', true),
                    ];
                }, $toolCalls));

                // Check if this exact tool call or same tool names were used before
                $isRepeated = in_array($toolNamesSignature, array_column($previousToolCalls, 'names')) ||
                    in_array($currentToolSignature, array_column($previousToolCalls, 'exact'));

                if ($isRepeated) {
                    Log::warning('ProcessJanChatJob: Detected repeated tool call - attempting recovery', [
                        'tool_calls' => $toolCalls,
                        'iteration' => $iteration,
                    ]);

                    // Add a very strong system message with explicit format to prevent tool calls
                    $messages[] = [
                        'role' => 'system',
                        'content' => '🛑 STOP - CRITICAL DIRECTIVE 🛑\n\nYou are repeating tool calls unnecessarily. The tools have ALREADY been executed and you have received ALL the data you need.\n\n✅ WHAT YOU MUST DO NOW:\n1. Look at the previous "tool" role messages in this conversation\n2. Extract the data/results from those messages\n3. Write a clear, direct answer using ONLY that existing data\n4. DO NOT call any functions or tools\n5. DO NOT request more information\n6. RESPOND WITH TEXT ONLY\n\n❌ YOU ARE FORBIDDEN FROM:\n- Calling ANY tools or functions\n- Using tool_calls in your response\n- Requesting additional data\n\nThe conversation already contains all necessary information. Provide your analysis NOW using plain text based on the tool results above.',
                    ];

                    // Try one more time with the strong directive
                    $payload['messages'] = $messages;
                    // Remove tools to force text-only response
                    $payload['tools'] = [];
                    unset($payload['tool_choice']);

                    try {
                        $janResponse = $this->sendToJanApi($payload);

                        // Check if it's trying to call tools again
                        $newMessage = $janResponse['choices'][0]['message'] ?? null;
                        if ($newMessage && isset($newMessage['tool_calls'])) {
                            // Still trying to call tools, force failure
                            Log::error('ProcessJanChatJob: AI still attempting tool calls after directive', [
                                'tool_calls' => $newMessage['tool_calls'],
                            ]);

                            event(new ChatFailed(
                                $this->userId,
                                $this->conversationId,
                                'The AI model is stuck in a tool-calling loop. Please try rephrasing your question or ask for a different analysis.',
                                ['iteration' => $iteration, 'tool_calls' => $toolCalls]
                            ));

                            return;
                        }

                        // Success - AI provided a text response
                        if ($newMessage) {
                            $messages[] = $newMessage;
                        }

                        $metrics = [
                            'iterations' => $iteration + 1,
                            'duration_seconds' => now()->diffInSeconds($startTime),
                            'message_count' => count($messages),
                        ];

                        event(new ChatCompleted(
                            $this->userId,
                            $this->conversationId,
                            [
                                'data' => $janResponse,
                                'conversation_id' => $this->conversationId,
                                'history_length' => count($messages),
                            ],
                            $metrics
                        ));

                        return;
                    } catch (\Exception $e) {
                        Log::error('ProcessJanChatJob: Recovery attempt failed', [
                            'error' => $e->getMessage(),
                        ]);

                        event(new ChatFailed(
                            $this->userId,
                            $this->conversationId,
                            'An error occurred while processing your request. Please try again.',
                            ['iteration' => $iteration, 'error' => $e->getMessage()]
                        ));

                        return;
                    }
                }

                $previousToolCalls[] = [
                    'names' => $toolNamesSignature,
                    'exact' => $currentToolSignature,
                    'iteration' => $iteration,
                ];

                // Execute tools
                try {
                    $toolResults = $toolExecutor->executeMultipleTools($toolCalls);

                    event(new ToolsCompleted(
                        $this->userId,
                        $this->conversationId,
                        $toolResults,
                        $iteration
                    ));
                } catch (\Exception $e) {
                    Log::error('ProcessJanChatJob: Tool execution failed', [
                        'error' => $e->getMessage(),
                        'tool_calls' => $toolCalls,
                    ]);

                    event(new ChatFailed(
                        $this->userId,
                        $this->conversationId,
                        'Tool execution failed: ' . $e->getMessage(),
                        ['iteration' => $iteration]
                    ));

                    throw $e;
                }

                // Add assistant message and tool results to conversation
                $assistantMessage = $janResponse['choices'][0]['message'] ?? null;
                if ($assistantMessage) {
                    $messages[] = $assistantMessage;
                }

                foreach ($toolResults as $index => $toolResult) {
                    // Make tool results very explicit
                    $messages[] = [
                        'role' => $toolResult['role'],
                        'tool_call_id' => $toolResult['tool_call_id'],
                        'content' => '[TOOL EXECUTION COMPLETE] ' . $toolResult['content'],
                    ];
                }

                // Update payload for next iteration
                $payload['messages'] = $messages;
            }

            // Max iterations reached
            Log::warning('ProcessJanChatJob: Max iterations reached', [
                'user_id' => $this->userId,
                'conversation_id' => $this->conversationId,
            ]);

            event(new ChatFailed(
                $this->userId,
                $this->conversationId,
                'Maximum tool call iterations reached',
                ['max_iterations' => $this->maxIterations]
            ));
        } catch (\Exception $e) {
            Log::error('ProcessJanChatJob: Fatal error', [
                'user_id' => $this->userId,
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            event(new ChatFailed(
                $this->userId,
                $this->conversationId,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    protected function sendToJanApi(array $payload): array
    {
        $janUrl = config('mcp.jan.url');
        $janModel = config('mcp.jan.model');
        $janAuthToken = config('mcp.jan.auth_token');
        $timeout = config('mcp.jan.timeout', 300);

        $payload['model'] = $payload['model'] ?? $janModel;
        $payload['max_tokens'] = $payload['max_tokens'] ?? config('mcp.jan.max_tokens', 4096);
        $payload['temperature'] = $payload['temperature'] ?? config('mcp.jan.temperature', 0.7);
        $payload['stream'] = $payload['stream'] ?? false;

        $url = rtrim($janUrl, '/') . '/v1/chat/completions';

        $request = Http::timeout($timeout)
            ->retry(2, 100, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });

        if (! empty($janAuthToken)) {
            $request = $request->withToken($janAuthToken);
        }

        $response = $request->post($url, $payload);

        if (! $response->successful()) {
            throw new \Exception(
                "Jan API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }

    protected function extractToolCalls(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;

        if (! $message) {
            return [];
        }

        $toolCalls = $message['tool_calls'] ?? [];

        return array_filter($toolCalls, function ($toolCall) {
            return isset($toolCall['function']['name']);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessJanChatJob: Job failed permanently', [
            'user_id' => $this->userId,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
        ]);

        event(new ChatFailed(
            $this->userId,
            $this->conversationId,
            'Job failed after multiple retries: ' . $exception->getMessage()
        ));
    }
}
