# Internal MCP Server Configuration

This Laravel application hosts its own MCP servers internally, so no external HTTP calls are needed.

## Configuration

### Internal MCP Server (Default)

For MCP servers hosted within this Laravel application:

```php
// config/mcp.php
'servers' => [
    'compressor_ai' => [
        'name' => 'Compressor AI',
        'route' => '/mcp/compressor-air-blower', // Internal Laravel route
        'type' => 'internal', // Use 'internal' for Laravel-hosted servers
        'enabled' => true,
        'timeout' => 30,
    ],
],
```

**Benefits:**

- ✅ No network latency
- ✅ No external dependencies
- ✅ Direct method calls within Laravel
- ✅ Better performance
- ✅ Easier debugging

### External MCP Server (Optional)

For MCP servers hosted on different systems:

```php
// config/mcp.php
'servers' => [
    'external_server' => [
        'name' => 'External MCP Server',
        'url' => 'http://another-server:3001', // External URL
        'type' => 'external', // Use 'external' for HTTP calls
        'enabled' => true,
        'timeout' => 30,
    ],
],
```

## How It Works

### Internal Server Communication

When `type => 'internal'`:

1. **Tool Discovery:**

    ```php
    // Makes an internal Laravel request (no HTTP)
    $request = Request::create('/mcp/compressor-air-blower', 'POST', ...);
    $response = app()->handle($request);
    ```

2. **Tool Execution:**
    ```php
    // Direct internal call to Laravel route
    $request = Request::create('/mcp/compressor-air-blower', 'POST', [
        'method' => 'tools/call',
        'params' => ['name' => 'tool-name', 'arguments' => [...]]
    ]);
    ```

### External Server Communication

When `type => 'external'`:

1. **Tool Discovery:**

    ```php
    // Makes HTTP GET request
    Http::get('http://external-server:3001/tools/list');
    ```

2. **Tool Execution:**
    ```php
    // Makes HTTP POST request
    Http::post('http://external-server:3001/tools/call', [...]);
    ```

## Registered MCP Servers

Check your `routes/ai.php` file for registered MCP servers:

```php
// Example from your application
Mcp::web('/mcp/compressor-air-blower', CompressorAirBlower::class);
Mcp::local('compressor-air-blower', CompressorAirBlower::class);
```

## Testing Internal MCP Server

### 1. Test MCP Server Directly

```bash
# Test the MCP server endpoint directly
curl -X POST http://localhost/mcp/compressor-air-blower \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list"
  }'
```

### 2. Test Tool Discovery Service

```php
use App\Services\McpToolService;

$toolService = app(McpToolService::class);
$tools = $toolService->getAllTools();

dd($tools); // Should show all available tools
```

### 3. Test Jan Integration

```bash
# Send a chat request (authenticated)
curl -X POST http://localhost/jan/chat \
  -H "Content-Type: application/json" \
  -H "Cookie: laravel_session=your-session-cookie" \
  -d '{
    "messages": [
      {"role": "user", "content": "Get compressor readings"}
    ]
  }'
```

## Environment Variables

No external URL needed for internal servers:

```env
# .env
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

## Debugging

### Enable Detailed Logging

Check `storage/logs/laravel.log` for:

```
[info] Fetching tools from internal MCP server: compressor_ai (route: /mcp/compressor-air-blower)
[info] Executing tool on internal MCP server (route: /mcp/compressor-air-blower, tool: compressor-air-blower-readings)
[info] Tool execution successful
```

### Common Issues

#### Issue: "Internal MCP server missing 'route' configuration"

**Solution:** Ensure `route` is set in config:

```php
'compressor_ai' => [
    'route' => '/mcp/compressor-air-blower',
    'type' => 'internal',
],
```

#### Issue: MCP server returns error

**Solution:** Test the MCP route directly:

```bash
php artisan route:list | grep mcp
```

Make sure the route exists and is accessible.

#### Issue: Tools not found

**Solution:**

1. Clear cache: `php artisan cache:clear`
2. Check MCP server is registered in `routes/ai.php`
3. Verify tool discovery logs in `storage/logs/laravel.log`

## Mixed Configuration Example

You can mix internal and external servers:

```php
'servers' => [
    // Internal Laravel-hosted MCP server
    'compressor_ai' => [
        'name' => 'Compressor AI',
        'route' => '/mcp/compressor-air-blower',
        'type' => 'internal',
        'enabled' => true,
        'timeout' => 30,
    ],

    // External MCP server on another machine
    'weather_service' => [
        'name' => 'Weather Service',
        'url' => 'http://weather-server:3002',
        'type' => 'external',
        'enabled' => true,
        'timeout' => 30,
    ],
],
```

## Performance Comparison

### Internal Server

- **Tool Discovery:** ~5-10ms
- **Tool Execution:** ~10-50ms (depending on tool complexity)
- **Total:** ~15-60ms per tool call

### External Server (localhost)

- **Tool Discovery:** ~50-100ms
- **Tool Execution:** ~60-150ms
- **Total:** ~110-250ms per tool call

### External Server (remote)

- **Tool Discovery:** ~100-500ms
- **Tool Execution:** ~120-600ms
- **Total:** ~220-1100ms per tool call

**Recommendation:** Use internal servers for best performance.

## Adding More Internal MCP Servers

### 1. Create the MCP Server Class

```php
// app/Mcp/Servers/MyNewServer.php
namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class MyNewServer extends Server
{
    public function tools(): array
    {
        return [
            $this->tool('my-tool')
                ->description('My tool description')
                ->parameter('param1', 'string', 'Parameter description'),
        ];
    }
}
```

### 2. Register in routes/ai.php

```php
use App\Mcp\Servers\MyNewServer;

Mcp::web('/mcp/my-new-server', MyNewServer::class);
```

### 3. Add to config/mcp.php

```php
'my_new_server' => [
    'name' => 'My New Server',
    'route' => '/mcp/my-new-server',
    'type' => 'internal',
    'enabled' => true,
    'timeout' => 30,
],
```

### 4. Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### 5. Test

```bash
curl http://localhost/jan/mcp/tools
```

Your new tool should appear in the list!

## Security Considerations

### Internal Servers

- Routes are protected by Laravel's authentication middleware
- No external network exposure
- Direct access control via Laravel policies

### External Servers

- Configure firewall rules
- Use authentication tokens if needed
- Consider VPN for production environments

## Production Recommendations

1. **Use Internal Servers:** When possible, host MCP servers within Laravel for best performance
2. **Enable Caching:** Set `MCP_CACHE_ENABLED=true` and reasonable TTL
3. **Monitor Logs:** Watch for MCP errors in `storage/logs/laravel.log`
4. **Set Timeouts:** Configure appropriate timeouts based on tool complexity
5. **Load Testing:** Test with expected concurrent users before deploying
