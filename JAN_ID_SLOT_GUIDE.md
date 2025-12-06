# Jan API Conversation Continuity with `id_slot`

## ⚡ TL;DR - The Better Way

**Use `id_slot` parameter for efficient conversation continuity!**

```php
$jan = app(JanService::class);

// First message
$response1 = $jan->chat('What is Laravel?', options: ['cache_prompt' => true]);
$reply1 = $jan->extractMessageFromResponse($response1);
$idSlot = $jan->extractIdSlot($response1); // Get the slot ID

// Continue conversation (only send NEW message + id_slot)
$response2 = $jan->chatCompletion([
    'model' => config('services.jan.default_model'),
    'messages' => [['role' => 'user', 'content' => 'Tell me more']],
    'id_slot' => $idSlot,  // 🎯 This maintains context!
    'cache_prompt' => true,
]);
```

## Two Methods Explained

### Method 1: Using `id_slot` (✅ RECOMMENDED)

**How it works:**

- Jan caches the conversation state in a "slot" on the server
- You only send the NEW message + the slot ID
- Jan retrieves cached context automatically
- Much faster and more efficient

**Benefits:**

- ✅ Lower latency (less data to send)
- ✅ Reduced token usage
- ✅ Better performance
- ✅ Maintained by Jan server

**Drawbacks:**

- ⚠️ Slot may be cleared if Jan restarts
- ⚠️ Limited number of slots available

### Method 2: Full Message History (Alternative)

**How it works:**

- You store ALL messages (user + assistant)
- Send ENTIRE history with each request
- No server-side caching needed

**Benefits:**

- ✅ Works even if Jan restarts
- ✅ Full control over conversation
- ✅ Easy to implement

**Drawbacks:**

- ❌ Higher latency (more data to send)
- ❌ More tokens used
- ❌ Slower with long conversations

## Using `id_slot` - Complete Guide

### Step 1: Enable Prompt Caching

Always set `cache_prompt: true` when you want to use `id_slot`:

```php
$response = $jan->chat('First message', options: [
    'cache_prompt' => true, // Required for id_slot
]);
```

### Step 2: Extract `id_slot` from Response

```php
$idSlot = $jan->extractIdSlot($response);

if ($idSlot !== null) {
    // Store this for next request
    session(['jan_id_slot' => $idSlot]);
}
```

### Step 3: Use `id_slot` in Next Request

#### Option A: Using `chatCompletion()` directly

```php
$response = $jan->chatCompletion([
    'model' => config('services.jan.default_model'),
    'messages' => [
        ['role' => 'user', 'content' => 'Next message'],
    ],
    'id_slot' => $idSlot,
    'cache_prompt' => true,
]);
```

#### Option B: Using `chatWithHistory()` helper

```php
$response = $jan->chatWithHistory(
    conversationHistory: [], // Can be empty when using id_slot
    newMessage: 'Next message',
    idSlot: $idSlot // Pass id_slot here
);
```

## Complete Examples

### Example 1: Simple Conversation with id_slot

```php
use App\Services\Jan\JanService;

$jan = app(JanService::class);

// First turn
$response1 = $jan->chat('What is PHP?', options: ['cache_prompt' => true]);
$reply1 = $jan->extractMessageFromResponse($response1);
$idSlot = $jan->extractIdSlot($response1);

echo "AI: {$reply1}\n";
echo "Slot ID: {$idSlot}\n\n";

// Second turn (using id_slot)
$response2 = $jan->chatCompletion([
    'model' => config('services.jan.default_model'),
    'messages' => [['role' => 'user', 'content' => 'What are its features?']],
    'id_slot' => $idSlot,
    'cache_prompt' => true,
]);

$reply2 = $jan->extractMessageFromResponse($response2);
echo "AI: {$reply2}\n";
```

### Example 2: Session-Based Conversation

```php
public function chat(Request $request, JanService $jan)
{
    $userMessage = $request->input('message');
    $idSlot = session('jan_id_slot');

    // Prepare request data
    $data = [
        'model' => config('services.jan.default_model'),
        'messages' => [
            ['role' => 'user', 'content' => $userMessage],
        ],
        'cache_prompt' => true,
    ];

    // Add id_slot if we have one from previous conversation
    if ($idSlot !== null) {
        $data['id_slot'] = $idSlot;
    }

    $response = $jan->chatCompletion($data);

    if ($response->successful()) {
        $reply = $jan->extractMessageFromResponse($response);

        // Save id_slot for next request
        $newIdSlot = $jan->extractIdSlot($response);
        if ($newIdSlot !== null) {
            session(['jan_id_slot' => $newIdSlot]);
        }

        return response()->json([
            'message' => $reply,
            'id_slot' => $newIdSlot,
        ]);
    }

    return response()->json(['error' => 'Failed'], 500);
}
```

### Example 3: Database-Backed Conversations

```php
use App\Models\Conversation;

public function chat($conversationId, $userMessage, JanService $jan)
{
    $conversation = Conversation::findOrFail($conversationId);

    $data = [
        'model' => config('services.jan.default_model'),
        'messages' => [
            ['role' => 'user', 'content' => $userMessage],
        ],
        'cache_prompt' => true,
    ];

    // Use stored id_slot if available
    if ($conversation->jan_id_slot) {
        $data['id_slot'] = $conversation->jan_id_slot;
    }

    $response = $jan->chatCompletion($data);

    if ($response->successful()) {
        $reply = $jan->extractMessageFromResponse($response);

        // Update id_slot in database
        $newIdSlot = $jan->extractIdSlot($response);
        if ($newIdSlot !== null) {
            $conversation->update(['jan_id_slot' => $newIdSlot]);
        }

        // Save messages
        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $reply],
        ]);

        return $reply;
    }

    throw new \Exception('Failed to get response');
}
```

### Example 4: Starting a New Conversation

```php
// To start a fresh conversation, simply don't pass id_slot
public function startNewConversation(JanService $jan)
{
    // Clear stored id_slot
    session()->forget('jan_id_slot');

    // Send first message without id_slot
    $response = $jan->chat('Hello!', options: ['cache_prompt' => true]);

    // Get new id_slot for this conversation
    $idSlot = $jan->extractIdSlot($response);
    session(['jan_id_slot' => $idSlot]);

    return $jan->extractMessageFromResponse($response);
}
```

## Best Practices

### ✅ Do's

1. **Always enable `cache_prompt`** when using `id_slot`
2. **Store `id_slot`** in session, database, or cache
3. **Check if `id_slot` exists** before using it
4. **Start new conversation** by not passing `id_slot`
5. **Handle missing `id_slot`** gracefully (Jan may not always return it)

### ❌ Don'ts

1. **Don't send full history** when using `id_slot` (defeats the purpose)
2. **Don't rely solely on `id_slot`** for critical data (it may be cleared)
3. **Don't share `id_slot`** between users (security/privacy)
4. **Don't assume `id_slot` persists forever** (may expire)

## Hybrid Approach: Best of Both Worlds

For maximum reliability, use BOTH methods:

```php
public function chat(Request $request, JanService $jan)
{
    $userMessage = $request->input('message');

    // Get history from database
    $history = $this->getConversationHistory($conversationId);

    // Get id_slot from session
    $idSlot = session('jan_id_slot');

    $data = [
        'model' => config('services.jan.default_model'),
        'messages' => $history, // Full history as backup
        'cache_prompt' => true,
    ];

    // Add new message
    $data['messages'][] = ['role' => 'user', 'content' => $userMessage];

    // Use id_slot for performance if available
    if ($idSlot !== null) {
        $data['id_slot'] = $idSlot;
    }

    $response = $jan->chatCompletion($data);

    // ... handle response
}
```

This way:

- If `id_slot` is available: Fast response with cached context
- If `id_slot` is missing: Still works using full history
- Best of both worlds!

## Troubleshooting

### Issue: `id_slot` is always null

**Possible causes:**

- `cache_prompt` not set to `true`
- Jan version doesn't support `id_slot`
- Jan server restarted

**Solution:**

```php
// Ensure cache_prompt is enabled
$response = $jan->chat('Message', options: [
    'cache_prompt' => true,
]);

// Check if id_slot is returned
$idSlot = $jan->extractIdSlot($response);
if ($idSlot === null) {
    // Fallback to full history method
}
```

### Issue: Conversation context lost

**Possible causes:**

- Jan server restarted
- `id_slot` expired
- Using wrong `id_slot`

**Solution:**

- Always store full conversation history as backup
- Use hybrid approach
- Start new conversation if context is lost

### Issue: Performance not improving

**Possible causes:**

- Still sending full history with `id_slot`
- `cache_prompt` not enabled
- `id_slot` not being used

**Solution:**

```php
// ❌ Wrong - defeats the purpose
$data = [
    'messages' => $fullHistory, // Don't send all messages
    'id_slot' => $idSlot,
];

// ✅ Correct - only new message
$data = [
    'messages' => [['role' => 'user', 'content' => $newMessage]],
    'id_slot' => $idSlot,
];
```

## API Reference

### `extractIdSlot(Response $response): ?int`

Extract `id_slot` from Jan API response.

**Returns:** Integer slot ID or `null` if not found

### `chatWithHistory(..., ?int $idSlot = null)`

Send chat with history and optional `id_slot`.

**Parameters:**

- `$conversationHistory` - Array of messages
- `$newMessage` - New user message
- `$model` - Optional model override
- `$idSlot` - Optional slot ID for continuity
- `$options` - Additional options

## Summary

| Feature         | id_slot       | Full History    |
| --------------- | ------------- | --------------- |
| Performance     | ⚡ Fast       | 🐢 Slower       |
| Token Usage     | 💰 Lower      | 💸 Higher       |
| Reliability     | ⚠️ May expire | ✅ Always works |
| Complexity      | 🎯 Simple     | 📚 More code    |
| **Recommended** | ✅ **Yes**    | Use as fallback |

**Use `id_slot` for better performance, but keep full history as backup for reliability!**

## Related Documentation

- Main Guide: `JAN_CONVERSATION_HISTORY.md`
- Quick Reference: `JAN_CONVERSATION_QUICK_REF.md`
- Examples: `examples/jan-conversation-history.php`
- Tests: `tests/Feature/JanConversationHistoryTest.php`
