<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool Executor Service
 *
 * This service executes tool calls by sending them to the appropriate
 * MCP server and returning the result.
 */
class McpToolExecutor
{
    protected McpToolService $toolService;

    /**
     * Create a new MCP Tool Executor instance.
     */
    public function __construct(McpToolService $toolService)
    {
        $this->toolService = $toolService;
    }

    /**
     * Execute a tool call.
     *
     * @param  string  $toolName  Full tool name (with server prefix)
     * @param  array  $arguments  Tool arguments
     * @return array Execution result
     *
     * @throws \Exception
     */
    public function executeTool(string $toolName, array $arguments = []): array
    {
        // Get the tool definition to extract server information
        $tool = $this->toolService->getTool($toolName);

        if (! $tool) {
            throw new \Exception("Tool not found: {$toolName}");
        }

        $serverKey = $tool['_mcp_server'] ?? null;
        $originalToolName = $tool['_mcp_original_name'] ?? null;

        if (! $serverKey || ! $originalToolName) {
            throw new \Exception("Invalid tool metadata for: {$toolName}");
        }

        // Get server configuration
        $serverConfig = config("mcp.servers.{$serverKey}");

        if (! $serverConfig) {
            throw new \Exception("MCP server not configured: {$serverKey}");
        }

        return $this->executeOnServer($serverConfig, $originalToolName, $arguments);
    }

    /**
     * Execute a tool on a specific MCP server.
     *
     * @param  array  $serverConfig  Server configuration
     * @param  string  $toolName  Original tool name (without prefix)
     * @param  array  $arguments  Tool arguments
     * @return array Execution result
     */
    protected function executeOnServer(array $serverConfig, string $toolName, array $arguments): array
    {
        $type = $serverConfig['type'] ?? 'external';

        if ($type === 'internal') {
            return $this->executeOnInternalServer($serverConfig, $toolName, $arguments);
        }

        return $this->executeOnExternalServer($serverConfig, $toolName, $arguments);
    }

    /**
     * Execute a tool on an internal Laravel MCP server route.
     *
     * @param  array  $serverConfig  Server configuration
     * @param  string  $toolName  Original tool name (without prefix)
     * @param  array  $arguments  Tool arguments
     * @return array Execution result
     */
    protected function executeOnInternalServer(array $serverConfig, string $toolName, array $arguments): array
    {
        $serverClass = $serverConfig['class'] ?? null;

        if (! $serverClass) {
            throw new \Exception("Internal MCP server missing 'class' configuration");
        }

        if (! class_exists($serverClass)) {
            throw new \Exception("MCP server class not found: {$serverClass}");
        }

        Log::info('Executing tool on internal MCP server', [
            'class' => $serverClass,
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
            // Instantiate the server with StdioTransport
            $transport = new \Laravel\Mcp\Server\Transport\StdioTransport('mcp-tool-exec-'.uniqid());
            $server = new $serverClass($transport);

            // Use reflection to access protected $tools property
            $reflection = new \ReflectionClass($server);
            $toolsProperty = $reflection->getProperty('tools');
            $toolClasses = $toolsProperty->getValue($server);

            // Find the tool by name
            $toolInstance = null;
            foreach ($toolClasses as $toolClass) {
                $tool = is_string($toolClass) ? app($toolClass) : $toolClass;

                if ($tool->name() === $toolName) {
                    $toolInstance = $tool;
                    break;
                }
            }

            if (! $toolInstance) {
                throw new \Exception("Tool not found: {$toolName}");
            }

            // Create a Laravel MCP Request object with the arguments
            $mcpRequest = new \Laravel\Mcp\Request($arguments);

            // Execute the tool
            $response = $toolInstance->handle($mcpRequest);

            Log::info('Tool execution successful', [
                'tool' => $toolName,
            ]);

            // Convert Laravel MCP Response to array format for Jan API
            $content = $response->content();

            return [
                'content' => is_array($content) ? $content : [
                    [
                        'type' => 'text',
                        'text' => (string) $content,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute a tool on an external HTTP MCP server.
     *
     * @param  array  $serverConfig  Server configuration
     * @param  string  $toolName  Original tool name (without prefix)
     * @param  array  $arguments  Tool arguments
     * @return array Execution result
     */
    protected function executeOnExternalServer(array $serverConfig, string $toolName, array $arguments): array
    {
        $url = rtrim($serverConfig['url'], '/').'/tools/call';
        $timeout = $serverConfig['timeout'] ?? 30;

        Log::info('Executing tool on external MCP server', [
            'url' => $url,
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->retry(
                    config('mcp.error_handling.retry_attempts', 3),
                    config('mcp.error_handling.retry_delay', 1000)
                )
                ->post($url, [
                    'name' => $toolName,
                    'arguments' => $arguments,
                ]);

            if (! $response->successful()) {
                throw new \Exception(
                    "Tool execution failed with status {$response->status()}: {$response->body()}"
                );
            }

            $result = $response->json();

            Log::info('Tool execution successful', [
                'tool' => $toolName,
                'result' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute multiple tool calls in sequence.
     *
     * @param  array  $toolCalls  Array of tool calls, each with 'name' and 'arguments'
     * @return array Array of execution results
     */
    public function executeMultipleTools(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'] ?? $toolCall['name'] ?? null;
            $arguments = $toolCall['function']['arguments'] ?? $toolCall['arguments'] ?? [];

            if (! $toolName) {
                Log::warning('Tool call missing name', ['toolCall' => $toolCall]);

                continue;
            }

            // If arguments is a JSON string, decode it
            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true) ?? [];
            }

            try {
                $result = $this->executeTool($toolName, $arguments);
                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? uniqid('call_'),
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode($result),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? uniqid('call_'),
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode([
                        'error' => $e->getMessage(),
                        'success' => false,
                    ]),
                ];
            }
        }

        return $results;
    }

    /**
     * Validate tool arguments against the tool's parameter schema.
     *
     * @param  string  $toolName  Tool name
     * @param  array  $arguments  Tool arguments
     */
    public function validateArguments(string $toolName, array $arguments): bool
    {
        $tool = $this->toolService->getTool($toolName);

        if (! $tool) {
            return false;
        }

        $parameters = $tool['function']['parameters'] ?? [];
        $required = $parameters['required'] ?? [];

        // Check if all required parameters are present
        foreach ($required as $requiredParam) {
            if (! array_key_exists($requiredParam, $arguments)) {
                Log::warning("Missing required parameter: {$requiredParam}", [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ]);

                return false;
            }
        }

        return true;
    }
}
