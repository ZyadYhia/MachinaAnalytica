# Jan API Authentication Setup

## Problem

Jan API returned a 401 error: "Missing authorization header"

## Solution

Added support for Bearer token authentication in the Jan MCP middleware.

## Configuration

### 1. Add to .env

```env
JAN_AUTH_TOKEN=your-bearer-token-here
```

**Note:** Leave empty if Jan API doesn't require authentication:

```env
JAN_AUTH_TOKEN=
```

### 2. Configuration File

The auth token is now configured in `config/mcp.php`:

```php
'jan' => [
    'url' => env('JAN_API_URL', 'http://localhost:1337'),
    'auth_token' => env('JAN_AUTH_TOKEN', ''), // Bearer token
    'model' => env('JAN_MODEL', 'llama3-8b-instruct'),
    'timeout' => env('JAN_TIMEOUT', 120),
    'max_tokens' => env('JAN_MAX_TOKENS', 4096),
    'temperature' => env('JAN_TEMPERATURE', 0.7),
    'stream' => env('JAN_STREAM', false),
],
```

### 3. How It Works

The middleware automatically adds the Bearer token to Jan API requests:

```php
// In JanMcpMiddleware::sendToJanApi()
$request = Http::timeout($timeout);

// Add Bearer token if configured
if (!empty($janAuthToken)) {
    $request = $request->withToken($janAuthToken);
}

$response = $request->post($url, $payload);
```

This sends the request with header:

```
Authorization: Bearer your-token-here
```

## Getting a Jan API Token

### Option 1: Jan API Key (if required)

If Jan requires an API key, check Jan's documentation or settings for how to generate one.

### Option 2: No Authentication

If your local Jan instance doesn't require authentication, leave the token empty:

```env
JAN_AUTH_TOKEN=
```

### Option 3: Custom Authentication

If Jan uses a different authentication method, you may need to modify the middleware.

## Testing

### 1. Without Authentication

```env
JAN_AUTH_TOKEN=
```

```bash
curl -X POST http://localhost/jan/chat \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "user", "content": "Hello"}
    ]
  }'
```

### 2. With Authentication

```env
JAN_AUTH_TOKEN=your-token-here
```

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

### Still Getting 401 Error

**Possible Causes:**

1. **Invalid Token:**
    - Check that the token is correct
    - Verify token hasn't expired
    - Try regenerating the token in Jan

2. **Token Format:**
    - Token should be the raw token value
    - Don't include "Bearer" prefix (added automatically)

    ✅ Correct: `JAN_AUTH_TOKEN=abc123xyz`
    ❌ Wrong: `JAN_AUTH_TOKEN=Bearer abc123xyz`

3. **Jan Configuration:**
    - Check Jan's authentication settings
    - Verify Jan is running and accessible
    - Test Jan API directly:

    ```bash
    curl http://localhost:1337/v1/models
    ```

4. **Environment Variables:**
    - Ensure `.env` file is loaded
    - Clear config cache:
    ```bash
    php artisan config:clear
    ```

### Check Configuration

```bash
# Verify Jan API URL
php artisan tinker
>>> config('mcp.jan.url')
=> "http://localhost:1337"

# Check if token is set (won't show the actual token for security)
>>> !empty(config('mcp.jan.auth_token'))
=> true  // Token is set
=> false // No token configured
```

### Debug Logs

Check `storage/logs/laravel.log` for:

```
[info] Sending request to Jan API (url: http://localhost:1337/v1/chat/completions)
[error] Jan API request failed with status 401: Missing authorization header
```

If you see this, the token is not being sent or is invalid.

## Alternative: Direct Jan API Call

If middleware authentication fails, you can bypass it:

### Using Existing JanService

```php
use App\Services\Jan\JanService;

$janService = new JanService(
    baseUrl: config('services.jan.url'),
    authToken: config('services.jan.auth_token')
);

$response = $janService->chatCompletion([
    'model' => 'llama3-8b-instruct',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ]
]);
```

### Direct HTTP Call

```php
use Illuminate\Support\Facades\Http;

$response = Http::baseUrl('http://localhost:1337')
    ->withToken('your-token-here') // If needed
    ->post('/v1/chat/completions', [
        'model' => 'llama3-8b-instruct',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello']
        ]
    ]);
```

## Security Notes

1. **Never commit tokens to git:**

    ```bash
    # .gitignore should include:
    .env
    ```

2. **Use environment variables:**
    - Production: Use secure environment variable management
    - Development: Use `.env` file (already in `.gitignore`)

3. **Rotate tokens regularly:**
    - Change tokens periodically
    - Invalidate old tokens after rotation

4. **Restrict token access:**
    - Only give tokens necessary permissions
    - Use separate tokens for different environments

## Summary

✅ **Added:** Bearer token authentication support  
✅ **Configuration:** `JAN_AUTH_TOKEN` in `.env`  
✅ **Optional:** Works with or without authentication  
✅ **Automatic:** Token is automatically added to requests  
✅ **Secure:** Token stored in environment variables

The middleware now properly handles Jan API authentication. Set `JAN_AUTH_TOKEN` in your `.env` file if Jan requires authentication, or leave it empty if not.
