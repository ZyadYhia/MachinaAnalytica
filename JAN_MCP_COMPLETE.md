# Jan API + Laravel MCP Integration - Complete Implementation

## Overview

This implementation provides automatic integration between Jan API (local LLM) and Laravel MCP servers. The system automatically discovers tools from MCP servers, injects them into Jan API requests, executes tool calls, and returns results - all transparently through middleware.

## Architecture

```
User Request → JanMcpMiddleware → Tool Discovery → Jan API with Tools
                     ↓
              Tool Execution ← Jan Tool Calls Response
                     ↓
              Final Response → User
```

## Key Components

### 1. Configuration (`config/mcp.php`)

```php
'servers' => [
    'compressor_ai' => [
        'name' => 'Compressor AI',
        'class' => \App\Mcp\Servers\CompressorAirBlower::class,
        'enabled' => true,
    ],
],

'jan' => [
    'base_url' => env('JAN_BASE_URL', 'http://localhost:1337'),
    'auth_token' => env('JAN_AUTH_TOKEN', ''),
    'model' => env('JAN_MODEL', 'Jan-v1-4B-Q4_K_M'),
    'max_tool_iterations' => 5,
],
```

### 2. Tool Discovery Service (`app/Services/McpToolService.php`)

**Key Features:**

- Discovers tools from internal Laravel MCP servers using reflection
- Caches tools for 1 hour
- Transforms MCP tool format to OpenAI function calling format
- Supports both internal (class-based) and external (HTTP) servers

**Tool Format:**

```json
{
    "type": "function",
    "function": {
        "name": "compressor_ai_compressor-air-blower-readings",
        "description": "Retrieve readings from sensors...",
        "parameters": {
            "type": "object",
            "properties": { ... }
        }
    },
    "_mcp_server": "compressor_ai",
    "_mcp_original_name": "compressor-air-blower-readings"
}
```

### 3. Tool Execution Service (`app/Services/McpToolExecutor.php`)

**Key Features:**

- Executes tool calls on MCP servers
- Parses tool names to identify server and tool
- Instantiates server classes with StdioTransport
- Creates Laravel MCP Request objects
- Handles tool responses and errors

**Execution Flow:**

1. Parse tool name: `compressor_ai_compressor-air-blower-readings`
2. Identify server: `compressor_ai`
3. Identify tool: `compressor-air-blower-readings`
4. Instantiate server class
5. Find tool instance
6. Execute `$tool->handle(new Request($arguments))`
7. Return formatted response

### 4. JanMCP Middleware (`app/Http/Middleware/JanMcpMiddleware.php`)

**Key Features:**

- Intercepts POST requests to `/jan/chat`
- Discovers and injects tools into request payload
- Handles iterative tool calling (max 5 iterations)
- Executes tool calls automatically
- Returns final response

**Request Flow:**

1. Original request arrives
2. Discover all available tools
3. Inject tools into messages payload
4. Send to Jan API
5. If response contains tool calls:
    - Execute each tool
    - Append results to messages
    - Send back to Jan API (repeat up to 5 times)
6. Return final text response to user

## Environment Setup

### Required Environment Variables

```env
# Jan API Configuration
JAN_BASE_URL=http://localhost:1337
JAN_AUTH_TOKEN='your-api-token-here'
JAN_MODEL=Jan-v1-4B-Q4_K_M
JAN_MAX_TOOL_ITERATIONS=5

# MCP Configuration
MCP_CACHE_DURATION=3600
```

### Get Jan Auth Token

1. Start Jan desktop application
2. Go to Settings → Advanced → API Server
3. Enable API Server
4. Copy the Bearer token
5. Add to `.env`: `JAN_AUTH_TOKEN='your-token'`

## Routes

### Web Routes (`routes/web.php`)

```php
Route::prefix('jan')->name('jan.')->middleware(['web', 'auth', 'jan.mcp'])->group(function () {
    Route::get('/', [JanController::class, 'index'])->name('index');
    Route::get('/models', [JanController::class, 'models'])->name('models');
    Route::post('/chat', [JanController::class, 'chat'])->name('chat');
    Route::get('/check-connection', [JanController::class, 'checkConnection'])->name('check-connection');

    // MCP Tool Management
    Route::get('/mcp/tools', [JanController::class, 'getMcpTools'])->name('mcp.tools');
    Route::post('/mcp/tools/refresh', [JanController::class, 'refreshMcpTools'])->name('mcp.tools.refresh');
});
```

## Testing

### 1. Test Tool Discovery

```bash
php artisan tinker --execute="
\$service = app(\App\Services\McpToolService::class);
\$tools = \$service->getAllTools();
echo 'Discovered tools: ' . count(\$tools) . PHP_EOL;
echo json_encode(\$tools, JSON_PRETTY_PRINT);
"
```

**Expected Output:**

```json
Discovered tools: 1
[
    {
        "type": "function",
        "function": {
            "name": "compressor_ai_compressor-air-blower-readings",
            "description": "Retrieve readings from the compressor air blower sensors...",
            "parameters": { ... }
        },
        "_mcp_server": "compressor_ai",
        "_mcp_original_name": "compressor-air-blower-readings"
    }
]
```

### 2. Test Jan API Connection

```bash
curl -X GET http://localhost:1337/v1/models \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected Output:**

```json
{
    "data": [
        {
            "created": 1,
            "id": "Jan-v1-4B-Q4_K_M",
            "object": "model",
            "owned_by": "user"
        }
    ],
    "object": "list"
}
```

### 3. Test Tool Endpoint

```bash
curl -X GET http://localhost/jan/mcp/tools \
  -H "Cookie: your-session-cookie"
```

**Expected Output:**

```json
{
    "tools": [
        {
            "type": "function",
            "function": {
                "name": "compressor_ai_compressor-air-blower-readings",
                "description": "...",
                "parameters": { ... }
            }
        }
    ],
    "total": 1
}
```

### 4. Test Chat with Tool Calling

```bash
curl -X POST http://localhost/jan/chat \
  -H "Content-Type: application/json" \
  -H "Cookie: your-session-cookie" \
  -d '{
    "messages": [
        {
            "role": "user",
            "content": "Show me the latest 5 compressor readings"
        }
    ]
}'
```

**Expected Flow:**

1. Middleware injects tools
2. Jan API receives tools and user message
3. Jan decides to call the tool
4. Middleware executes tool automatically
5. Results sent back to Jan
6. Jan generates natural language response
7. Final response returned to user

## How It Works

### Internal MCP Server Communication

**Before (❌ Wrong Approach):**

```php
// Tried to make HTTP requests to routes
$response = Http::get('http://localhost/mcp/compressor-air-blower?method=tools/list');
```

**After (✅ Correct Approach):**

```php
// Direct class instantiation
$transport = new \Laravel\Mcp\Server\Transport\StdioTransport('session-'.uniqid());
$server = new \App\Mcp\Servers\CompressorAirBlower($transport);

// Access protected $tools property via reflection
$reflection = new \ReflectionClass($server);
$toolsProperty = $reflection->getProperty('tools');
$toolClasses = $toolsProperty->getValue($server);

// Instantiate and use tools
foreach ($toolClasses as $toolClass) {
    $tool = app($toolClass);
    $toolData = $tool->toArray(); // Get properly formatted schema
}
```

### Tool Execution

```php
// Parse tool name
[$serverKey, $toolName] = $this->parseToolName('compressor_ai_compressor-air-blower-readings');

// Instantiate server
$transport = new \Laravel\Mcp\Server\Transport\StdioTransport('tool-exec-'.uniqid());
$server = new $serverClass($transport);

// Find tool
$reflection = new \ReflectionClass($server);
$toolsProperty = $reflection->getProperty('tools');
$toolClasses = $toolsProperty->getValue($server);
$tool = app($toolClasses[0]);

// Execute
$mcpRequest = new \Laravel\Mcp\Request($arguments);
$mcpResponse = $tool->handle($mcpRequest);
```

## Middleware Registration

**bootstrap/app.php:**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'jan.mcp' => \App\Http\Middleware\JanMcpMiddleware::class,
    ]);
})
```

## Adding New MCP Servers

### 1. Create Server Class

```bash
php artisan mcp:server MyNewServer
```

### 2. Register in config/mcp.php

```php
'servers' => [
    'my_server' => [
        'name' => 'My New Server',
        'class' => \App\Mcp\Servers\MyNewServer::class,
        'enabled' => true,
    ],
],
```

### 3. Create Tools

```bash
php artisan mcp:tool MyNewTool --server=MyNewServer
```

### 4. Clear Cache

```bash
php artisan cache:clear
```

Tools will be automatically discovered and injected into Jan API requests!

## Troubleshooting

### Issue: Tools not discovered

**Solution:**

```bash
php artisan cache:clear
php artisan config:clear
```

### Issue: Jan API returns 401

**Solution:**

- Check `JAN_AUTH_TOKEN` in `.env`
- Token must NOT have quotes when used in curl
- Token MUST have quotes in `.env` file

### Issue: Tool execution fails

**Check logs:**

```bash
tail -f storage/logs/laravel.log
```

**Common causes:**

- Invalid tool arguments
- Server class doesn't exist
- Tool class doesn't exist
- Database connection issues

### Issue: Infinite loop in tool calling

**Solution:**

- Check `JAN_MAX_TOOL_ITERATIONS` in config
- Default is 5 iterations
- Middleware will stop after max iterations

## Performance Considerations

### Caching

- Tools are cached for 1 hour (configurable via `MCP_CACHE_DURATION`)
- Clear cache when adding/removing tools: `Cache::forget('mcp.tools')`
- Refresh endpoint: `POST /jan/mcp/tools/refresh`

### Transport Sessions

- Each tool discovery creates a unique session ID
- Format: `mcp-tool-discovery-{uniqid}`
- Each execution creates a unique session ID
- Format: `mcp-tool-exec-{uniqid}`

## Security

### Authentication

- All routes require authentication (`auth` middleware)
- Jan API requires Bearer token
- MCP servers run in application context (same permissions)

### Authorization

Consider adding authorization checks:

```php
Gate::define('use-jan-api', function (User $user) {
    return $user->isAdmin();
});

Route::middleware(['auth', 'can:use-jan-api', 'jan.mcp'])->group(...);
```

## Future Enhancements

1. **Streaming Support**: Stream responses from Jan API
2. **Tool Selection**: Let users enable/disable specific tools
3. **Usage Tracking**: Track tool usage and costs
4. **Rate Limiting**: Limit requests per user
5. **Conversation History**: Store and retrieve past conversations
6. **Multi-Model Support**: Switch between different Jan models
7. **Tool Permissions**: Fine-grained control over tool access

## Complete Integration Test

Create `tests/Feature/JanMcpIntegrationTest.php`:

```php
it('can chat with Jan using MCP tools', function () {
    // Arrange
    $user = User::factory()->create();
    CompressorAirBlower::factory()->count(10)->create();

    // Act
    $response = $this->actingAs($user)->postJson('/jan/chat', [
        'messages' => [
            ['role' => 'user', 'content' => 'Show me the latest 5 compressor readings']
        ],
    ]);

    // Assert
    $response->assertSuccessful();
    $response->assertJsonStructure([
        'id',
        'object',
        'created',
        'model',
        'choices' => [
            '*' => [
                'index',
                'message' => [
                    'role',
                    'content',
                ],
                'finish_reason',
            ],
        ],
    ]);
});
```

## Summary

This implementation provides:

✅ Automatic tool discovery from MCP servers  
✅ Transparent tool injection into Jan API requests  
✅ Automatic tool execution  
✅ Iterative tool calling support  
✅ Comprehensive error handling  
✅ Caching for performance  
✅ Authentication and security  
✅ Easy extensibility (just add new MCP servers)

The system is fully functional and ready for use!
