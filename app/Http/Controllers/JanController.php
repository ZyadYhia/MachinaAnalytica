<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendJanPromptRequest;
use App\Services\Jan\JanService;
use App\Services\McpToolService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class JanController extends Controller
{
    public function __construct(
        protected JanService $janService,
        protected McpToolService $toolService
    ) {}

    /**
     * Display the Jan chat interface.
     */
    public function index(): Response
    {
        return Inertia::render('jan/index');
    }

    /**
     * Get list of available models.
     */
    public function models(): JsonResponse
    {
        try {
            $response = $this->janService->listModels();

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch models',
                'error' => $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jan service is not available',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Send a chat completion request.
     */
    public function chat(SendJanPromptRequest $request): JsonResponse
    {
        // Increase PHP's default socket timeout for Jan AI long-running requests
        ini_set('default_socket_timeout', '300');

        // Disable max execution time for this request
        set_time_limit(300);

        try {
            $response = $this->janService->chatCompletion($request->validated());

            if ($response->successful()) {
                $data = $response->json();

                // Extract the full response structure from Jan API
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $data['id'] ?? null,
                        'object' => $data['object'] ?? 'chat.completion',
                        'created' => $data['created'] ?? time(),
                        'model' => $data['model'] ?? $request->input('model'),
                        'system_fingerprint' => $data['system_fingerprint'] ?? null,
                        'choices' => $data['choices'] ?? [],
                        'usage' => $data['usage'] ?? null,
                        'timings' => $data['timings'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Chat completion failed',
                'error' => $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during chat completion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a chat message with conversation history.
     * Stores conversation in session based on conversation ID.
     *
     * Note: When the jan.mcp middleware is active, it handles:
     * - Building conversation history from session
     * - MCP tool execution
     * - Saving conversation back to session
     *
     * This method only runs if middleware is bypassed or for direct API calls.
     */
    public function chatWithHistory(SendJanPromptRequest $request): JsonResponse
    {
        // If the middleware already handled this (check for the response attribute),
        // the middleware has already returned, so this code only runs without middleware

        // Increase PHP's default socket timeout for Jan AI long-running requests
        ini_set('default_socket_timeout', '300');
        set_time_limit(300);

        try {
            $conversationId = $request->input('conversation_id', 'default');
            $newMessage = $request->input('message');

            // Validate that message is provided
            if (empty($newMessage)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The message field is required.',
                    'errors' => [
                        'message' => ['The message field is required.'],
                    ],
                ], 422);
            }

            $systemPrompt = $request->input('system_prompt');

            // Get conversation history from session
            $conversationHistory = session("jan_conversations.{$conversationId}", []);

            // Debug: Log what we retrieved from session
            \Log::info('Controller: Retrieved conversation history', [
                'conversation_id' => $conversationId,
                'message_count' => count($conversationHistory),
            ]);

            // Add system prompt if provided and not already in history
            if ($systemPrompt && (empty($conversationHistory) || $conversationHistory[0]['role'] !== 'system')) {
                array_unshift($conversationHistory, [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ]);
            }

            // Add new user message
            $conversationHistory[] = [
                'role' => 'user',
                'content' => $newMessage,
            ];

            // Send request with full conversation history
            $response = $this->janService->chatCompletion([
                'model' => $request->input('model', config('services.jan.default_model')),
                'messages' => $conversationHistory,
                'temperature' => $request->input('temperature', 0.8),
                'max_tokens' => $request->input('max_tokens', 2048),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $assistantMessage = $data['choices'][0]['message']['content'] ?? null;

                // Add assistant's response to conversation history
                if ($assistantMessage) {
                    $conversationHistory[] = [
                        'role' => 'assistant',
                        'content' => $assistantMessage,
                    ];

                    // Save updated conversation history to session
                    session(["jan_conversations.{$conversationId}" => $conversationHistory]);

                    \Log::info('Controller: Saved conversation history', [
                        'conversation_id' => $conversationId,
                        'message_count' => count($conversationHistory),
                    ]);
                }

                // Return response in same format as original chat endpoint
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $data['id'] ?? null,
                        'object' => $data['object'] ?? 'chat.completion',
                        'created' => $data['created'] ?? time(),
                        'model' => $data['model'] ?? $request->input('model'),
                        'system_fingerprint' => $data['system_fingerprint'] ?? null,
                        'choices' => $data['choices'] ?? [],
                        'usage' => $data['usage'] ?? null,
                        'timings' => $data['timings'] ?? null,
                        // Add metadata for debugging
                        'conversation_id' => $conversationId,
                        'history_length' => count($conversationHistory),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Chat completion failed',
                'error' => $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during chat completion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear a conversation history.
     */
    public function clearConversation(string $conversationId = 'default'): JsonResponse
    {
        session()->forget("jan_conversations.{$conversationId}");

        return response()->json([
            'success' => true,
            'message' => "Conversation '{$conversationId}' cleared successfully",
        ]);
    }

    /**
     * Get conversation history.
     */
    public function getConversation(string $conversationId = 'default'): JsonResponse
    {
        $conversationHistory = session("jan_conversations.{$conversationId}", []);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversationId,
            'messages' => $conversationHistory,
            'message_count' => count($conversationHistory),
        ]);
    }

    /**
     * Check Jan service connection.
     */
    public function checkConnection(): JsonResponse
    {
        $isAvailable = $this->janService->checkConnection();

        return response()->json([
            'success' => $isAvailable,
            'message' => $isAvailable
                ? 'Jan service is available'
                : 'Jan service is not available',
        ], $isAvailable ? 200 : 503);
    }

    /**
     * Get all available MCP tools.
     *
     * This endpoint allows clients to see what tools are available
     * from all configured MCP servers.
     */
    public function tools(): JsonResponse
    {
        try {
            $tools = $this->toolService->getAllTools();

            return response()->json([
                'tools' => $tools,
                'count' => count($tools),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch tools',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific tool by name.
     */
    public function tool(string $toolName): JsonResponse
    {
        try {
            $tool = $this->toolService->getTool($toolName);

            if (! $tool) {
                return response()->json([
                    'error' => 'Tool not found',
                    'tool_name' => $toolName,
                ], 404);
            }

            return response()->json([
                'tool' => $tool,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch tool',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear the MCP tools cache.
     *
     * This is useful when MCP servers have been updated and you want
     * to force a refresh of available tools.
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->toolService->clearCache();

            return response()->json([
                'message' => 'MCP tools cache cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get MCP configuration and server status.
     */
    public function status(): JsonResponse
    {
        try {
            $servers = config('mcp.servers', []);
            $serverStatus = [];

            foreach ($servers as $key => $server) {
                $serverStatus[$key] = [
                    'name' => $server['name'] ?? $key,
                    'url' => $server['url'] ?? 'Not configured',
                    'enabled' => $server['enabled'] ?? true,
                    'timeout' => $server['timeout'] ?? 30,
                ];
            }

            return response()->json([
                'servers' => $serverStatus,
                'jan' => [
                    'url' => config('mcp.jan.url'),
                    'model' => config('mcp.jan.model'),
                    'timeout' => config('mcp.jan.timeout'),
                ],
                'cache' => [
                    'enabled' => config('mcp.cache.enabled'),
                    'ttl' => config('mcp.cache.ttl'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'service' => 'Jan MCP Integration',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
