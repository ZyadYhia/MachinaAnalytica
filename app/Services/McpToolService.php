<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool Discovery Service
 *
 * This service is responsible for discovering and fetching available tools
 * from all configured MCP servers. It caches the tools for performance.
 */
class McpToolService
{
    /**
     * Fetch all available tools from configured MCP servers.
     *
     * @return array Array of tools in Jan API compatible format
     */
    public function getAllTools(): array
    {
        $cacheKey = $this->getCacheKey();

        // Return cached tools if caching is enabled
        if ($this->isCacheEnabled() && Cache::has($cacheKey)) {
            Log::info('MCP tools loaded from cache');

            return Cache::get($cacheKey);
        }

        $tools = [];
        $servers = config('mcp.servers', []);

        foreach ($servers as $serverKey => $serverConfig) {
            if (! $this->isServerEnabled($serverConfig)) {
                continue;
            }

            try {
                $serverTools = $this->fetchToolsFromServer($serverKey, $serverConfig);
                $tools = array_merge($tools, $serverTools);
            } catch (\Exception $e) {
                $this->handleServerError($serverKey, $e);
            }
        }

        // Cache the tools if caching is enabled
        if ($this->isCacheEnabled()) {
            Cache::put($cacheKey, $tools, config('mcp.cache.ttl', 3600));
            Log::info('MCP tools cached', ['count' => count($tools)]);
        }

        return $tools;
    }

    /**
     * Fetch tools from a single MCP server.
     *
     * @param  string  $serverKey  Unique server identifier
     * @param  array  $serverConfig  Server configuration
     * @return array Array of tools
     */
    protected function fetchToolsFromServer(string $serverKey, array $serverConfig): array
    {
        $type = $serverConfig['type'] ?? 'external';

        if ($type === 'internal') {
            return $this->fetchToolsFromInternalServer($serverKey, $serverConfig);
        }

        return $this->fetchToolsFromExternalServer($serverKey, $serverConfig);
    }

    /**
     * Fetch tools from an internal Laravel MCP server route.
     *
     * @param  string  $serverKey  Unique server identifier
     * @param  array  $serverConfig  Server configuration
     * @return array Array of tools
     */
    protected function fetchToolsFromInternalServer(string $serverKey, array $serverConfig): array
    {
        $serverClass = $serverConfig['class'] ?? null;

        if (! $serverClass) {
            throw new \Exception("Internal MCP server {$serverKey} missing 'class' configuration");
        }

        if (! class_exists($serverClass)) {
            throw new \Exception("MCP server class not found: {$serverClass}");
        }

        Log::info("Fetching tools from internal MCP server: {$serverKey}", ['class' => $serverClass]);

        try {
            // Instantiate the server with StdioTransport
            $transport = new \Laravel\Mcp\Server\Transport\StdioTransport('mcp-tool-discovery-'.uniqid());
            $server = new $serverClass($transport);

            // Use reflection to access protected $tools property
            $reflection = new \ReflectionClass($server);
            $toolsProperty = $reflection->getProperty('tools');
            $toolClasses = $toolsProperty->getValue($server);

            $tools = [];

            foreach ($toolClasses as $toolClass) {
                // Instantiate each tool class
                $tool = is_string($toolClass) ? app($toolClass) : $toolClass;

                // Use the tool's toArray() method which returns properly formatted schema
                $tools[] = $tool->toArray();
            }

            return $this->transformToolsForJan($tools, $serverKey);
        } catch (\Exception $e) {
            Log::error("Error fetching tools from internal MCP server: {$serverKey}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch tools from an external HTTP MCP server.
     *
     * @param  string  $serverKey  Unique server identifier
     * @param  array  $serverConfig  Server configuration
     * @return array Array of tools
     */
    protected function fetchToolsFromExternalServer(string $serverKey, array $serverConfig): array
    {
        $url = rtrim($serverConfig['url'], '/').'/tools/list';
        $timeout = $serverConfig['timeout'] ?? 30;

        Log::info("Fetching tools from external MCP server: {$serverKey}", ['url' => $url]);

        $response = Http::timeout($timeout)
            ->retry(config('mcp.error_handling.retry_attempts', 3), config('mcp.error_handling.retry_delay', 1000))
            ->get($url);

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch tools from {$serverKey}: ".$response->status());
        }

        $data = $response->json();
        $tools = $data['tools'] ?? [];

        // Transform MCP tools to Jan API compatible format
        return $this->transformToolsForJan($tools, $serverKey);
    }

    /**
     * Transform MCP tools to Jan API compatible format.
     *
     * Jan API expects tools in OpenAI function calling format:
     * {
     *   "type": "function",
     *   "function": {
     *     "name": "tool_name",
     *     "description": "Tool description",
     *     "parameters": { ... JSON schema ... }
     *   }
     * }
     *
     * @param  array  $tools  MCP tools
     * @param  string  $serverKey  Server identifier to prefix tool names
     * @return array Transformed tools
     */
    protected function transformToolsForJan(array $tools, string $serverKey): array
    {
        return array_map(function ($tool) use ($serverKey) {
            return [
                'type' => 'function',
                'function' => [
                    // Prefix tool name with server key to avoid conflicts
                    'name' => "{$serverKey}_{$tool['name']}",
                    'description' => $tool['description'] ?? 'No description available',
                    'parameters' => $tool['inputSchema'] ?? [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
                // Store metadata for internal use
                '_mcp_server' => $serverKey,
                '_mcp_original_name' => $tool['name'],
            ];
        }, $tools);
    }

    /**
     * Clear the tools cache.
     */
    public function clearCache(): void
    {
        $cacheKey = $this->getCacheKey();
        Cache::forget($cacheKey);
        Log::info('MCP tools cache cleared');
    }

    /**
     * Get a specific tool by name.
     *
     * @param  string  $toolName  Full tool name (with server prefix)
     * @return array|null Tool definition or null if not found
     */
    public function getTool(string $toolName): ?array
    {
        $tools = $this->getAllTools();

        foreach ($tools as $tool) {
            if ($tool['function']['name'] === $toolName) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Check if a server is enabled.
     *
     * @param  array  $serverConfig  Server configuration
     */
    protected function isServerEnabled(array $serverConfig): bool
    {
        return $serverConfig['enabled'] ?? true;
    }

    /**
     * Check if caching is enabled.
     */
    protected function isCacheEnabled(): bool
    {
        return config('mcp.cache.enabled', true);
    }

    /**
     * Get the cache key for storing tools.
     */
    protected function getCacheKey(): string
    {
        return config('mcp.cache.key_prefix', 'mcp_tools_').'all';
    }

    /**
     * Handle errors from MCP servers.
     *
     * @param  string  $serverKey  Server identifier
     * @param  \Exception  $exception  Exception that occurred
     *
     * @throws \Exception
     */
    protected function handleServerError(string $serverKey, \Exception $exception): void
    {
        Log::error("Error fetching tools from MCP server: {$serverKey}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // If fail_silently is false, re-throw the exception
        if (! config('mcp.error_handling.fail_silently', false)) {
            throw $exception;
        }
    }
}
