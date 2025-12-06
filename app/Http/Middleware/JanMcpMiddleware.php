<?php

namespace App\Http\Middleware;

use App\Jobs\ProcessJanChatJob;
use App\Services\McpToolExecutor;
use App\Services\McpToolService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jan MCP Middleware
 *
 * This middleware intercepts requests to Jan API, injects MCP tools,
 * handles tool calls, and returns the final response.
 *
 * Flow:
 * 1. Intercept incoming request
 * 2. Check if async mode is requested
 * 3. If async: dispatch ProcessJanChatJob and return 202 Accepted
 * 4. If sync: Execute synchronously (original flow)
 */
class JanMcpMiddleware
{
    protected McpToolService $toolService;

    protected McpToolExecutor $toolExecutor;

    /**
     * Maximum number of tool call iterations to prevent infinite loops.
     */
    protected int $maxIterations = 5;

    /**
     * Create a new middleware instance.
     */
    public function __construct(McpToolService $toolService, McpToolExecutor $toolExecutor)
    {
        $this->toolService = $toolService;
        $this->toolExecutor = $toolExecutor;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if async mode is requested
        $async = $request->input('async', $request->header('X-Async-Processing')) === '1' ||
            $request->input('async', $request->header('X-Async-Processing')) === 'true' ||
            $request->input('async', $request->header('X-Async-Processing')) === true;

        if ($async) {
            return $this->handleAsync($request);
        }

        // Default to synchronous processing
        return $this->handleSync($request, $next);
    }

    /**
     * Handle async request by dispatching a job
     */
    protected function handleAsync(Request $request): Response
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required for async processing',
                ], 401);
            }

            $payload = $request->all();
            $isHistoryEndpoint = $request->is('jan/chat/history');

            // Generate unique conversation ID
            $conversationId = $payload['conversation_id'] ?? 'default';
            if ($conversationId === 'default') {
                $conversationId = Str::uuid()->toString();
            }

            // Prepare job parameters
            $conversationHistory = [];
            $systemPrompt = null;
            $message = '';

            if ($isHistoryEndpoint) {
                $conversationHistory = session("jan_conversations.{$conversationId}", []);
                // Use provided system prompt or default to a clear, concise one with strict tool usage rules
                $systemPrompt = $payload['system_prompt'] ?? 'You are a helpful AI assistant. IMPORTANT RULES: 1) Call each tool ONLY ONCE per conversation. 2) After receiving tool results, IMMEDIATELY analyze and present them - do NOT call tools again. 3) If you already have tool results, answer based on that data. 4) Never repeat tool calls. 5) Always end with a direct text response.';
                $message = $payload['message'] ?? '';

                if (empty($message)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Message is required',
                    ], 422);
                }
            } else {
                $message = $payload['message'] ?? '';
                if (isset($payload['messages'])) {
                    $conversationHistory = $payload['messages'];
                }
            }

            // Dispatch the job
            ProcessJanChatJob::dispatch(
                $user->id,
                $conversationId,
                $payload,
                $conversationHistory,
                $systemPrompt,
                $message
            );

            Log::info('Jan MCP Middleware: Dispatched async job', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
            ]);

            // Return 202 Accepted with tracking info
            return response()->json([
                'success' => true,
                'message' => 'Request queued for processing',
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'channel' => "private-jan-chat.{$user->id}.{$conversationId}",
                'async' => true,
            ], 202);
        } catch (\Exception $e) {
            Log::error('Jan MCP Middleware: Async dispatch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to queue request',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle synchronous request (original flow)
     */
    protected function handleSync(Request $request, Closure $next): Response
    {
        try {
            // Fetch all available MCP tools
            $tools = [];
            try {
                $tools = $this->toolService->getAllTools();
                Log::info('Jan MCP Middleware: Injecting tools', ['count' => count($tools)]);
            } catch (\Exception $e) {
                Log::warning('Jan MCP Middleware: Failed to fetch tools, continuing without tools', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Get the original request payload
            $payload = $request->all();

            // Handle both 'messages' (array) and 'message' (single string) formats
            // For /chat/history endpoint, we need to build messages from conversation history
            $isHistoryEndpoint = $request->is('jan/chat/history');

            if ($isHistoryEndpoint && isset($payload['message']) && ! isset($payload['messages'])) {
                Log::info('Jan MCP Middleware: History endpoint detected, building messages from conversation');

                // Get conversation history from session
                $conversationId = $payload['conversation_id'] ?? 'default';
                $conversationHistory = session("jan_conversations.{$conversationId}", []);

                // Add system prompt if provided and not in history
                $systemPrompt = $payload['system_prompt'] ?? null;
                if ($systemPrompt && (empty($conversationHistory) || $conversationHistory[0]['role'] !== 'system')) {
                    array_unshift($conversationHistory, [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ]);
                }

                // Add new user message
                $conversationHistory[] = [
                    'role' => 'user',
                    'content' => $payload['message'],
                ];

                // Set messages for middleware to use
                $payload['messages'] = $conversationHistory;

                // Store for later saving
                $request->attributes->set('conversation_id', $conversationId);
                $request->attributes->set('is_history_endpoint', true);
            } elseif ($isHistoryEndpoint && ! isset($payload['message']) && ! isset($payload['messages'])) {
                // No message provided for history endpoint, pass to controller for validation
                Log::info('Jan MCP Middleware: No message provided, passing to controller');

                return $next($request);
            }

            // Only inject tools if we have any available
            if (! empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto'; // Let Jan decide when to use tools
            }

            // Initialize conversation history
            $messages = $payload['messages'] ?? [];
            $iteration = 0;
            $previousToolCalls = []; // Track previous tool calls to detect loops

            // Keep calling Jan API until it returns a final response (no tool calls)
            while ($iteration < $this->maxIterations) {
                $iteration++;

                Log::info("Jan MCP Middleware: Iteration {$iteration}");

                // Send request to Jan API
                try {
                    $janResponse = $this->sendToJanApi($payload);
                } catch (\Exception $e) {
                    Log::error('Jan MCP Middleware: Jan API call failed', [
                        'iteration' => $iteration,
                        'error' => $e->getMessage(),
                    ]);

                    // Return error response
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to communicate with Jan API',
                        'message' => $e->getMessage(),
                    ], 500);
                }

                // Check if Jan wants to call tools (only if tools are available)
                $toolCalls = ! empty($tools) ? $this->extractToolCalls($janResponse) : [];

                if (empty($toolCalls)) {
                    // No tool calls, return the final response wrapped in success structure
                    Log::info('Jan MCP Middleware: Final response received');

                    // If this was a history endpoint, save the conversation back to session
                    if ($request->attributes->get('is_history_endpoint')) {
                        $conversationId = $request->attributes->get('conversation_id');

                        // Add final assistant response to messages
                        $assistantMessage = $janResponse['choices'][0]['message'] ?? null;
                        if ($assistantMessage) {
                            $messages[] = $assistantMessage;
                        }

                        // Save to session
                        session(["jan_conversations.{$conversationId}" => $messages]);

                        Log::info('Jan MCP Middleware: Saved conversation to session', [
                            'conversation_id' => $conversationId,
                            'message_count' => count($messages),
                        ]);

                        // Add metadata to response
                        $janResponse['conversation_id'] = $conversationId;
                        $janResponse['history_length'] = count($messages);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $janResponse,
                    ]);
                }

                // Execute tool calls
                Log::info('Jan MCP Middleware: Executing tool calls', ['count' => count($toolCalls)]);

                // Check if we're repeating the same tool calls (potential infinite loop)
                // Create simplified signature focusing on tool names (args may vary slightly)
                $currentToolNames = array_map(function ($call) {
                    return $call['function']['name'] ?? 'unknown';
                }, $toolCalls);
                sort($currentToolNames);
                $currentToolSignature = implode(',', $currentToolNames);

                // Also check exact match with arguments
                $exactSignature = json_encode(array_map(function ($call) {
                    return [
                        'name' => $call['function']['name'] ?? 'unknown',
                        'args' => $call['function']['arguments'] ?? [],
                    ];
                }, $toolCalls));

                // Detect loop if same tool names appear OR exact same calls
                if (
                    in_array($currentToolSignature, array_column($previousToolCalls, 'names')) ||
                    in_array($exactSignature, array_column($previousToolCalls, 'exact'))
                ) {
                    Log::warning('Jan MCP Middleware: Detected repeated tool calls - attempting recovery', [
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
                        $recoveryResponse = $this->sendToJanApi($payload);

                        // Check if it's still trying to call tools
                        $newMessage = $recoveryResponse['choices'][0]['message'] ?? null;
                        if ($newMessage && isset($newMessage['tool_calls'])) {
                            // Still trying to call tools, return error
                            Log::error('Jan MCP Middleware: AI still attempting tool calls after directive', [
                                'tool_calls' => $newMessage['tool_calls'],
                            ]);

                            return response()->json([
                                'success' => false,
                                'error' => 'Tool execution loop detected',
                                'message' => 'The AI model is stuck in a tool-calling loop. Please try rephrasing your question or ask for a different analysis.',
                            ], 500);
                        }

                        // Success - AI provided a text response
                        Log::info('Jan MCP Middleware: Recovery successful, AI provided text response');

                        return response()->json([
                            'success' => true,
                            'data' => $recoveryResponse,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Jan MCP Middleware: Recovery attempt failed', [
                            'error' => $e->getMessage(),
                        ]);

                        return response()->json([
                            'success' => false,
                            'error' => 'Recovery failed',
                            'message' => 'An error occurred while processing your request. Please try again.',
                        ], 500);
                    }
                }

                $previousToolCalls[] = [
                    'names' => $currentToolSignature,
                    'exact' => $exactSignature,
                    'iteration' => $iteration,
                ];

                try {
                    // Set a timeout for tool execution
                    set_time_limit(300); // 5 minutes for tool execution

                    $toolResults = $this->toolExecutor->executeMultipleTools($toolCalls);
                } catch (\Exception $e) {
                    Log::error('Jan MCP Middleware: Tool execution failed', [
                        'error' => $e->getMessage(),
                        'tool_calls' => $toolCalls,
                    ]);

                    // Return response without tool execution but with error info
                    return response()->json([
                        'success' => true,
                        'data' => $janResponse,
                        'warning' => 'Some tools could not be executed: ' . $e->getMessage(),
                        'error_details' => [
                            'message' => $e->getMessage(),
                            'failed_tools' => array_map(function ($call) {
                                return $call['function']['name'] ?? 'unknown';
                            }, $toolCalls),
                        ],
                    ]);
                }

                // Add assistant's message with tool calls to conversation
                $assistantMessage = $janResponse['choices'][0]['message'] ?? null;
                if ($assistantMessage) {
                    $messages[] = $assistantMessage;
                }

                // Add tool results to conversation
                foreach ($toolResults as $toolResult) {
                    $messages[] = $toolResult;
                }

                // Update payload for next iteration
                $payload['messages'] = $messages;
            }

            // Max iterations reached
            Log::warning('Jan MCP Middleware: Max iterations reached');

            return response()->json([
                'success' => false,
                'error' => 'Maximum tool call iterations reached',
                'max_iterations' => $this->maxIterations,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Jan MCP Middleware Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a request to Jan API.
     *
     * @param  array  $payload  Request payload
     * @return array Response from Jan API
     *
     * @throws \Exception
     */
    protected function sendToJanApi(array $payload): array
    {
        $janUrl = config('mcp.jan.url');
        $janModel = config('mcp.jan.model');
        $janAuthToken = config('mcp.jan.auth_token');
        // Increase timeout to 5 minutes for complex tool executions
        $timeout = config('mcp.jan.timeout', 300);

        // Ensure model is set
        $payload['model'] = $payload['model'] ?? $janModel;

        // Set default parameters if not provided
        $payload['max_tokens'] = $payload['max_tokens'] ?? config('mcp.jan.max_tokens', 4096);
        $payload['temperature'] = $payload['temperature'] ?? config('mcp.jan.temperature', 0.7);
        $payload['stream'] = $payload['stream'] ?? config('mcp.jan.stream', false);

        $url = rtrim($janUrl, '/') . '/v1/chat/completions';

        Log::info('Sending request to Jan API', [
            'url' => $url,
            'model' => $payload['model'],
            'messages_count' => count($payload['messages'] ?? []),
            'tools_count' => count($payload['tools'] ?? []),
        ]);

        // Build the request with optional authentication and retry logic
        $request = Http::timeout($timeout)
            ->retry(2, 100, function ($exception) {
                // Only retry on timeout or connection errors
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });

        // Add Bearer token if configured
        if (! empty($janAuthToken)) {
            $request = $request->withToken($janAuthToken);
        }

        try {
            $response = $request->post($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Jan API connection timeout', [
                'error' => $e->getMessage(),
                'timeout' => $timeout,
            ]);

            throw new \Exception(
                "Jan API request timed out after {$timeout} seconds. The model may be taking too long to respond or MCP tools are hanging."
            );
        }

        if (! $response->successful()) {
            Log::error('Jan API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            throw new \Exception(
                "Jan API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        // Check if response is actually JSON
        $contentType = $response->header('Content-Type');
        if (! str_contains($contentType, 'application/json')) {
            Log::error('Jan API returned non-JSON response', [
                'content_type' => $contentType,
                'body' => $response->body(),
            ]);

            throw new \Exception(
                "Jan API returned non-JSON response (Content-Type: {$contentType})"
            );
        }

        try {
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to parse Jan API response as JSON', [
                'error' => $e->getMessage(),
                'body' => $response->body(),
            ]);

            throw new \Exception(
                "Failed to parse Jan API response: {$e->getMessage()}"
            );
        }
    }

    /**
     * Extract tool calls from Jan API response.
     *
     * @param  array  $response  Jan API response
     * @return array Array of tool calls
     */
    protected function extractToolCalls(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;

        if (! $message) {
            return [];
        }

        $toolCalls = $message['tool_calls'] ?? [];

        // Filter out any invalid tool calls
        return array_filter($toolCalls, function ($toolCall) {
            return isset($toolCall['function']['name']);
        });
    }

    /**
     * Set the maximum number of tool call iterations.
     */
    public function setMaxIterations(int $maxIterations): void
    {
        $this->maxIterations = $maxIterations;
    }
}
