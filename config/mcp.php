<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Servers Configuration
    |--------------------------------------------------------------------------
    |
    | This array contains all configured MCP servers. Each server should have
    | a unique key, a base URL, and optional authentication credentials.
    |
    */
    'servers' => [
        'compressor_ai' => [
            'name' => 'Compressor AI',
            'class' => \App\Mcp\Servers\CompressorAirBlower::class, // Laravel MCP Server class
            'type' => 'internal', // 'internal' for Laravel MCP servers, 'external' for HTTP URLs
            'enabled' => env('MCP_COMPRESSOR_AI_ENABLED', true),
            'timeout' => 30, // seconds
        ],
        // Add more MCP servers as needed
        // External MCP server example:
        // 'external_server' => [
        //     'name' => 'External MCP Server',
        //     'url' => env('MCP_EXTERNAL_URL', 'http://localhost:3001'),
        //     'type' => 'external',
        //     'enabled' => env('MCP_EXTERNAL_ENABLED', true),
        //     'timeout' => 30,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Jan API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to Jan API server.
    |
    */
    'jan' => [
        'url' => env('JAN_API_URL', 'http://localhost:1337'),
        'auth_token' => env('JAN_AUTH_TOKEN', ''), // Optional: Bearer token for Jan API
        'model' => env('JAN_MODEL', 'llama3-8b-instruct'),
        'timeout' => env('JAN_TIMEOUT', 120),
        'max_tokens' => env('JAN_MAX_TOKENS', 4096),
        'temperature' => env('JAN_TEMPERATURE', 0.7),
        'stream' => env('JAN_STREAM', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | How long to cache MCP tool definitions (in seconds).
    | Set to 0 to disable caching.
    |
    */
    'cache' => [
        'enabled' => env('MCP_CACHE_ENABLED', true),
        'ttl' => env('MCP_CACHE_TTL', 3600), // 1 hour
        'key_prefix' => 'mcp_tools_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure how errors from MCP servers should be handled.
    |
    */
    'error_handling' => [
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'fail_silently' => false, // If true, continues without tools if MCP fails
    ],
];
