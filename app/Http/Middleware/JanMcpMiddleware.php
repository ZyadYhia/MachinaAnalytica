<?php

namespace App\Http\Middleware;

use App\Services\McpToolExecutor;
use App\Services\McpToolService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jan MCP Middleware
 *
 * This middleware intercepts requests to Jan API, injects MCP tools,
 * handles tool calls, and returns the final response.
 *
 * Flow:
 * 1. Intercept incoming request
 * 2. Fetch available MCP tools
 * 3. Inject tools into the request payload
 * 4. Send request to Jan API
 * 5. Check if Jan wants to call any tools
 * 6. Execute tool calls via MCP servers
 * 7. Send tool results back to Jan API
 * 8. Return final response to client
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
                $currentToolSignature = json_encode(array_map(function ($call) {
                    $functionName = $call['function']['name'] ?? 'unknown';
                    $arguments = $call['function']['arguments'] ?? [];

                    return ['name' => $functionName, 'args' => $arguments];
                }, $toolCalls));

                if (in_array($currentToolSignature, $previousToolCalls)) {
                    Log::warning('Jan MCP Middleware: Detected repeated tool calls, breaking loop', [
                        'tool_calls' => $toolCalls,
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $janResponse,
                        'warning' => 'Tool execution loop detected. Returning last response.',
                    ]);
                }

                $previousToolCalls[] = $currentToolSignature;

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
                        'warning' => 'Some tools could not be executed: '.$e->getMessage(),
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

        $url = rtrim($janUrl, '/').'/v1/chat/completions';

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
