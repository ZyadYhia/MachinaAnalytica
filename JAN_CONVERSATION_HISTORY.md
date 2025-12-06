# Jan API Conversation History Guide

## Overview

Unlike some AI services that use persistent chat/session IDs, **Jan's API maintains conversation context by passing the full message history** in each request. The `id` field returned in responses (e.g., `"chatcmpl-aDxXMphiHxUZMVZfl4lJSdRnbmM7ZgG9"`) is simply a unique identifier for that specific completion, **not a conversation or session ID**.

## How Conversation Context Works

To continue a conversation with Jan:

1. **Store all previous messages** (both user and assistant messages)
2. **Send the entire conversation history** in the `messages` array with each new request
3. **Optionally include a system prompt** as the first message to set the AI's behavior

## New Methods in JanService

The `JanService` has been enhanced with methods to help you manage conversation history:

### 1. `chatWithHistory()`

Continue a conversation by passing the full history plus a new message.

```php
public function chatWithHistory(
    array $conversationHistory,
    string $newMessage,
    ?string $model = null,
    array $options = []
): Response
```

**Parameters:**

- `$conversationHistory` - Array of message objects with 'role' and 'content'
- `$newMessage` - The new user message to add
- `$model` - Optional model override (defaults to config setting)
- `$options` - Additional Jan API options (temperature, max_tokens, etc.)

### 2. `extractMessageFromResponse()`

Extract the assistant's message content from a chat completion response.

```php
public function extractMessageFromResponse(Response $response): ?string
```

**Returns:** The assistant's message content or `null` if not found/failed

### 3. `buildMessage()`

Helper to build a properly formatted message object.

```php
public function buildMessage(string $role, string $content): array
```

**Parameters:**

- `$role` - Either 'user', 'assistant', or 'system'
- `$content` - The message content

**Returns:** Array with 'role' and 'content' keys

## Usage Examples

### Example 1: Simple Multi-Turn Conversation

```php
use App\Services\Jan\JanService;

$jan = app(JanService::class);

// Start with empty history
$history = [];

// First message
$response1 = $jan->chat('What is Laravel?');
$reply1 = $jan->extractMessageFromResponse($response1);

// Add to history
$history[] = $jan->buildMessage('user', 'What is Laravel?');
$history[] = $jan->buildMessage('assistant', $reply1);

// Second message (with context)
$response2 = $jan->chatWithHistory($history, 'What are its main features?');
$reply2 = $jan->extractMessageFromResponse($response2);

// Add to history
$history[] = $jan->buildMessage('user', 'What are its main features?');
$history[] = $jan->buildMessage('assistant', $reply2);

// Third message (with full context)
$response3 = $jan->chatWithHistory($history, 'Show me a code example');
$reply3 = $jan->extractMessageFromResponse($response3);
```

### Example 2: Using System Prompts

```php
// Initialize with system prompt
$history = [
    [
        'role' => 'system',
        'content' => 'You are a Laravel expert. Provide concise, accurate answers.',
    ],
];

$response = $jan->chatWithHistory(
    $history,
    'How do I create a migration?',
    options: ['temperature' => 0.7]
);

$reply = $jan->extractMessageFromResponse($response);
```

### Example 3: Storing in Session (Web App)

```php
use App\Services\Jan\JanService;
use Illuminate\Http\Request;

public function chat(Request $request, JanService $jan)
{
    // Get or initialize conversation history from session
    $history = session('jan_conversation', []);

    // Add system prompt if first message
    if (empty($history)) {
        $history[] = [
            'role' => 'system',
            'content' => 'You are a helpful AI assistant.',
        ];
    }

    $userMessage = $request->input('message');

    // Send with full history
    $response = $jan->chatWithHistory(
        conversationHistory: $history,
        newMessage: $userMessage,
        options: [
            'temperature' => 0.8,
            'max_tokens' => 2048,
        ]
    );

    if ($response->successful()) {
        $assistantReply = $jan->extractMessageFromResponse($response);

        // Update history in session
        $history[] = $jan->buildMessage('user', $userMessage);
        $history[] = $jan->buildMessage('assistant', $assistantReply);
        session(['jan_conversation' => $history]);

        return response()->json([
            'success' => true,
            'message' => $assistantReply,
            'metadata' => $response->json('usage'),
        ]);
    }

    return response()->json(['error' => 'Failed to get response'], 500);
}
```

### Example 4: Database-Backed Conversations

```php
// Assuming you have Conversation and Message models

public function chat($conversationId, $userMessage, JanService $jan)
{
    $conversation = Conversation::with('messages')->findOrFail($conversationId);

    // Build history from database
    $history = $conversation->messages->map(fn($msg) => [
        'role' => $msg->role,
        'content' => $msg->content,
    ])->toArray();

    // Send to Jan
    $response = $jan->chatWithHistory($history, $userMessage);

    if ($response->successful()) {
        $assistantReply = $jan->extractMessageFromResponse($response);

        // Save both messages
        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $assistantReply],
        ]);

        return $assistantReply;
    }

    throw new \Exception('Failed to get response');
}
```

### Example 5: Using All Available Parameters

```php
$history = [
    ['role' => 'system', 'content' => 'You are a code reviewer.'],
    ['role' => 'user', 'content' => 'Review this code...'],
    ['role' => 'assistant', 'content' => 'Here are my observations...'],
];

$response = $jan->chatWithHistory(
    conversationHistory: $history,
    newMessage: "Can you explain the security concerns?",
    options: [
        'temperature' => 0.8,
        'max_tokens' => 2048,
        'top_p' => 0.95,
        'top_k' => 40,
        'repeat_penalty' => 1.1,
        'presence_penalty' => 0,
        'frequency_penalty' => 0,
        'stop' => [],
        // Add any other parameters you need
    ]
);
```

## Understanding the Response Structure

```json
{
    "choices": [
        {
            "finish_reason": "stop",
            "index": 0,
            "message": {
                "role": "assistant",
                "content": "The actual reply text here..."
            }
        }
    ],
    "created": 1764976242,
    "id": "chatcmpl-aDxXMphiHxUZMVZfl4lJSdRnbmM7ZgG9",
    "model": "Jan-v1-4B-Q4_K_M",
    "object": "chat.completion",
    "system_fingerprint": "b1-af41bf6",
    "timings": {
        "cache_n": 866,
        "prompt_n": 543,
        "prompt_ms": 951.944,
        "prompt_per_token_ms": 1.7531197053406997
    },
    "usage": {
        "completion_tokens": 777,
        "prompt_tokens": 1409,
        "total_tokens": 2186
    },
    "success": true
}
```

### Key Points:

- **`id`**: Unique identifier for THIS specific completion (not a session ID)
- **`choices[0].message.content`**: The assistant's actual reply
- **`usage`**: Token usage statistics for this request
- **`timings`**: Performance metrics
- **Context is maintained by the messages array**, not by an ID

## Storage Options

### 1. Session (Simple, Single User)

- Good for: Simple web apps, single-user conversations
- Pros: Easy to implement, no database needed
- Cons: Lost when session expires, doesn't persist across devices

```php
session(['jan_conversation' => $history]);
$history = session('jan_conversation', []);
```

### 2. Database (Persistent, Multi-User)

- Good for: Production apps, multi-user systems, conversation history features
- Pros: Persistent, searchable, auditable
- Cons: Requires database schema, more complex

```php
// Store in database with Eloquent
$conversation->messages()->create([
    'role' => 'user',
    'content' => $message,
]);
```

### 3. Cache (Temporary, Fast)

- Good for: High-performance needs, temporary conversations
- Pros: Fast access, automatic expiration
- Cons: Not durable, may be evicted

```php
Cache::put("jan_conversation_{$userId}", $history, now()->addHours(24));
$history = Cache::get("jan_conversation_{$userId}", []);
```

## Clearing/Resetting Conversations

```php
// Clear session
session()->forget('jan_conversation');

// Clear cache
Cache::forget("jan_conversation_{$userId}");

// Clear database
$conversation->messages()->delete();
```

## Available Jan Parameters

All these parameters can be passed in the `options` array:

```php
[
    'stream' => false,
    'max_tokens' => 2048,
    'stop' => [],
    'temperature' => 0.8,
    'dynatemp_range' => 0,
    'dynatemp_exponent' => 1,
    'top_k' => 40,
    'top_p' => 0.95,
    'min_p' => 0.05,
    'typical_p' => 1,
    'n_predict' => -1,
    'n_indent' => 0,
    'n_keep' => 0,
    'presence_penalty' => 0,
    'frequency_penalty' => 0,
    'repeat_penalty' => 1.1,
    'repeat_last_n' => 64,
    'dry_multiplier' => 0,
    'dry_base' => 1.75,
    'dry_allowed_length' => 2,
    'dry_penalty_last_n' => -1,
    'dry_sequence_breakers' => ["\n", ':', '"', '*'],
    'xtc_probability' => 0,
    'xtc_threshold' => 0.1,
    'mirostat' => 0,
    'mirostat_tau' => 5,
    'mirostat_eta' => 0.1,
    'grammar' => 'string',
    'json_schema' => (object) [],
    'seed' => -1,
    'ignore_eos' => false,
    'logit_bias' => (object) [],
    'n_probs' => 0,
    'min_keep' => 0,
    't_max_predict_ms' => 0,
    'id_slot' => -1,
    'cache_prompt' => true,
    'return_tokens' => false,
    'samplers' => [
        'dry',
        'top_k',
        'typ_p',
        'top_p',
        'min_p',
        'xtc',
        'temperature',
    ],
    'timings_per_token' => false,
    'return_progress' => false,
    'post_sampling_probs' => false,
    'response_fields' => [],
    'lora' => [],
    'multimodal_data' => [],
]
```

## Testing

Comprehensive tests are available in `tests/Feature/JanConversationHistoryTest.php`. Run them with:

```bash
php artisan test --filter=JanConversationHistoryTest
```

## Additional Resources

- **Example Code**: `examples/jan-conversation-history.php`
- **Service Class**: `app/Services/Jan/JanService.php`
- **Tests**: `tests/Feature/JanConversationHistoryTest.php`
- **Jan API Documentation**: [Jan Docs](https://jan.ai/docs/)

## Best Practices

1. **Include system prompts** at the start of conversations to guide AI behavior
2. **Limit history length** for long conversations to avoid token limits (keep last N messages)
3. **Store conversations** in database for production apps with multiple users
4. **Handle errors gracefully** - always check if response was successful
5. **Clear old conversations** periodically to save storage/memory
6. **Monitor token usage** from response metadata to optimize costs
7. **Test with various conversation lengths** to ensure performance

## Troubleshooting

### Issue: "Token limit exceeded"

**Solution**: Limit conversation history to recent messages (e.g., last 20 messages)

```php
// Keep only last 20 messages (plus system prompt if present)
$recentHistory = array_slice($history, -20);
```

### Issue: "Conversation doesn't maintain context"

**Solution**: Ensure you're passing the FULL history with each request

### Issue: "Response is slow with long conversations"

**Solution**: Either limit history or increase timeout in JanService

```php
// In JanService, the timeout is already set to 300 seconds for chat completions
```
