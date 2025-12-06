# Jan API with MCP Integration

This Laravel 12 implementation provides seamless integration between Jan API and local MCP (Model Context Protocol) servers, enabling automatic tool discovery and execution.

## Architecture Overview

```
Client Request
    ↓
Laravel Route (with jan.mcp middleware)
    ↓
JanMcpMiddleware
    ├─→ McpToolService (fetch available tools)
    ├─→ Inject tools into request
    ├─→ Send to Jan API
    ├─→ Detect tool calls
    ├─→ McpToolExecutor (execute tools on MCP servers)
    └─→ Return final response
```

## Components

### 1. Configuration (`config/mcp.php`)

- MCP server URLs and settings
- Jan API configuration
- Cache settings
- Error handling options

### 2. McpToolService (`app/Services/McpToolService.php`)

- Discovers tools from all configured MCP servers
- Transforms MCP tools to Jan API compatible format
- Caches tools for performance
- Manages tool metadata

### 3. McpToolExecutor (`app/Services/McpToolExecutor.php`)

- Executes tool calls on appropriate MCP servers
- Handles multiple tool calls
- Validates tool arguments
- Returns execution results

### 4. JanMcpMiddleware (`app/Http/Middleware/JanMcpMiddleware.php`)

- Intercepts Jan chat requests
- Injects MCP tools automatically
- Handles tool call loop until final response
- Prevents infinite loops with max iterations

### 5. JanController (`app/Http/Controllers/JanController.php`)

- Chat endpoint (with MCP middleware)
- Tool management endpoints
- Cache management
- Status and health checks

## Setup

### 1. Environment Configuration

Add to your `.env` file:

```env
# MCP Server Configuration
MCP_COMPRESSOR_AI_URL=http://localhost:3000
MCP_COMPRESSOR_AI_ENABLED=true

# Jan API Configuration
JAN_API_URL=http://localhost:1337
JAN_MODEL=llama3-8b-instruct
JAN_TIMEOUT=120
JAN_MAX_TOKENS=4096
JAN_TEMPERATURE=0.7
JAN_STREAM=false

# MCP Cache Configuration
MCP_CACHE_ENABLED=true
MCP_CACHE_TTL=3600
```

### 2. Add More MCP Servers

Edit `config/mcp.php` to add more servers:

```php
'servers' => [
    'compressor_ai' => [
        'name' => 'Compressor AI',
        'url' => env('MCP_COMPRESSOR_AI_URL', 'http://localhost:3000'),
        'enabled' => env('MCP_COMPRESSOR_AI_ENABLED', true),
        'timeout' => 30,
    ],
    'another_server' => [
        'name' => 'Another MCP Server',
        'url' => env('MCP_ANOTHER_SERVER_URL', 'http://localhost:3001'),
        'enabled' => env('MCP_ANOTHER_SERVER_ENABLED', true),
        'timeout' => 30,
    ],
],
```

### 3. Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## Available Endpoints

### Chat with MCP Tools (Authenticated)

```
POST /jan/chat
```

**Request Body:**

```json
{
    "messages": [
        {
            "role": "user",
            "content": "What are the compressor air blower readings?"
        }
    ],
    "model": "llama3-8b-instruct",
    "temperature": 0.7,
    "max_tokens": 4096
}
```

**Response:**

```json
{
    "id": "chatcmpl-xxx",
    "object": "chat.completion",
    "created": 1701878400,
    "model": "llama3-8b-instruct",
    "choices": [
        {
            "index": 0,
            "message": {
                "role": "assistant",
                "content": "Here are the latest readings..."
            },
            "finish_reason": "stop"
        }
    ],
    "usage": {
        "prompt_tokens": 50,
        "completion_tokens": 100,
        "total_tokens": 150
    }
}
```

### Get Available Tools

```
GET /jan/mcp/tools
```

### Get Specific Tool

```
GET /jan/mcp/tools/{toolName}
```

### Clear Tools Cache

```
POST /jan/mcp/cache/clear
```

### Get MCP Status

```
GET /jan/mcp/status
```

### Health Check

```
GET /jan/health
```

## How It Works

### 1. Tool Discovery

When a chat request comes in, `McpToolService` fetches all available tools from configured MCP servers:

```
GET http://localhost:3000/tools/list
```

Expected response:

```json
{
    "tools": [
        {
            "name": "compressor-air-blower-readings",
            "description": "Get compressor air blower sensor readings",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "limit": {
                        "type": "integer",
                        "description": "Maximum number of readings"
                    }
                }
            }
        }
    ]
}
```

### 2. Tool Transformation

Tools are transformed to Jan API format:

```json
{
    "type": "function",
    "function": {
        "name": "compressor_ai_compressor-air-blower-readings",
        "description": "Get compressor air blower sensor readings",
        "parameters": {
            "type": "object",
            "properties": {
                "limit": {
                    "type": "integer",
                    "description": "Maximum number of readings"
                }
            }
        }
    },
    "_mcp_server": "compressor_ai",
    "_mcp_original_name": "compressor-air-blower-readings"
}
```

### 3. Tool Execution

When Jan decides to call a tool, the middleware:

1. Extracts tool calls from Jan's response
2. Uses `McpToolExecutor` to execute each tool
3. Sends results back to Jan API
4. Gets final response

Tool execution request:

```
POST http://localhost:3000/tools/call
Content-Type: application/json

{
  "name": "compressor-air-blower-readings",
  "arguments": {
    "limit": 10
  }
}
```

### 4. Conversation Flow

```
User: "What are the latest compressor readings?"
  ↓
Jan API: [wants to call tool: compressor_ai_compressor-air-blower-readings]
  ↓
MCP Server: [returns sensor data]
  ↓
Jan API: [formats response with data]
  ↓
User: "Here are the latest readings: [data]"
```

## Middleware Flow

```php
JanMcpMiddleware::handle()
├─ Fetch MCP tools (McpToolService::getAllTools())
├─ Inject tools into request payload
├─ LOOP (max 5 iterations):
│  ├─ Send request to Jan API
│  ├─ Check for tool calls
│  ├─ If tool calls exist:
│  │  ├─ Execute tools (McpToolExecutor::executeMultipleTools())
│  │  ├─ Add results to conversation
│  │  └─ Continue loop
│  └─ If no tool calls:
│     └─ Return final response
└─ Return response to client
```

## Error Handling

### MCP Server Unavailable

```json
{
    "error": "Failed to fetch tools from compressor_ai: Connection refused"
}
```

**Solution:** Check if MCP server is running and URL is correct.

### Max Iterations Reached

```json
{
    "error": "Maximum tool call iterations reached",
    "max_iterations": 5
}
```

**Solution:** Jan API might be stuck in a loop. Check Jan logs.

### Tool Not Found

```json
{
    "error": "Tool not found: invalid_tool_name"
}
```

**Solution:** Clear cache or verify tool exists on MCP server.

## Debugging

### Enable Detailed Logging

Check `storage/logs/laravel.log` for detailed information about:

- Tool discovery
- Tool execution
- Jan API requests
- Middleware flow

### Common Log Messages

```
[info] MCP tools loaded from cache
[info] Fetching tools from MCP server: compressor_ai
[info] Jan MCP Middleware: Injecting tools (count: 5)
[info] Jan MCP Middleware: Iteration 1
[info] Jan MCP Middleware: Executing tool calls (count: 1)
[info] Executing tool on MCP server (tool: compressor-air-blower-readings)
[info] Tool execution successful
[info] Jan MCP Middleware: Final response received
```

## Performance Optimization

### 1. Tool Caching

Tools are cached for 1 hour by default. Adjust in `config/mcp.php`:

```php
'cache' => [
    'enabled' => env('MCP_CACHE_ENABLED', true),
    'ttl' => env('MCP_CACHE_TTL', 3600), // seconds
],
```

### 2. Disable Caching for Development

```env
MCP_CACHE_ENABLED=false
```

### 3. Manual Cache Clear

```bash
# Via artisan
php artisan cache:clear

# Via endpoint
curl -X POST http://localhost/jan/mcp/cache/clear
```

## Adding New MCP Servers

1. **Add to config/mcp.php:**

```php
'weather_server' => [
    'name' => 'Weather MCP Server',
    'url' => env('MCP_WEATHER_URL', 'http://localhost:3002'),
    'enabled' => env('MCP_WEATHER_ENABLED', true),
    'timeout' => 30,
],
```

2. **Add to .env:**

```env
MCP_WEATHER_URL=http://localhost:3002
MCP_WEATHER_ENABLED=true
```

3. **Clear cache:**

```bash
php artisan config:clear
curl -X POST http://localhost/jan/mcp/cache/clear
```

## Security Considerations

1. **CSRF Protection:** The `/jan/chat` endpoint has CSRF disabled for API-like usage
2. **Authentication:** Routes are behind `auth` middleware
3. **Rate Limiting:** Consider adding rate limiting for production
4. **MCP Server Trust:** Only configure trusted MCP servers

## Testing

### Manual Testing

1. **Check MCP Status:**

```bash
curl http://localhost/jan/mcp/status
```

2. **Get Available Tools:**

```bash
curl http://localhost/jan/mcp/tools
```

3. **Send Chat Request:**

```bash
curl -X POST http://localhost/jan/chat \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "user", "content": "Hello"}
    ]
  }'
```

## Troubleshooting

### Issue: Tools Not Showing Up

**Solution:**

1. Check MCP server is running: `curl http://localhost:3000/tools/list`
2. Check server is enabled in config
3. Clear cache: `php artisan cache:clear`
4. Check logs: `tail -f storage/logs/laravel.log`

### Issue: Tool Execution Fails

**Solution:**

1. Verify tool name matches (check prefix)
2. Verify arguments match schema
3. Check MCP server logs
4. Test direct MCP call: `curl -X POST http://localhost:3000/tools/call -d '{"name":"tool-name","arguments":{}}'`

### Issue: Jan API Not Responding

**Solution:**

1. Check Jan is running: `curl http://localhost:1337/v1/models`
2. Verify JAN_API_URL in .env
3. Check timeout settings
4. Increase `JAN_TIMEOUT` if needed

## Advanced Configuration

### Custom Tool Name Prefix

By default, tools are prefixed with their server key. To customize:

Edit `McpToolService::transformToolsForJan()`:

```php
'name' => "{$serverKey}_{$tool['name']}", // Default
'name' => "custom_prefix_{$tool['name']}", // Custom
```

### Adjust Max Iterations

To prevent infinite loops, max iterations is set to 5. To adjust:

```php
// In a service provider or controller
$middleware = app(JanMcpMiddleware::class);
$middleware->setMaxIterations(10);
```

### Fail Silently on MCP Errors

To continue without tools if MCP servers fail:

```php
'error_handling' => [
    'fail_silently' => true, // Continue without tools if MCP fails
],
```

## Contributing

When adding new features:

1. Follow Laravel 12 conventions
2. Add comprehensive logging
3. Update this documentation
4. Write tests
5. Consider error handling

## License

This implementation is part of the MachinaAnalytica project.
