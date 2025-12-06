# Jan Conversation History - Quick Reference

## TL;DR

**Jan doesn't use session IDs.** You maintain context by sending the full message history with each request.

## Basic Pattern

```php
$jan = app(JanService::class);
$history = [];

// First turn
$response = $jan->chat('Hello');
$reply = $jan->extractMessageFromResponse($response);
$history[] = $jan->buildMessage('user', 'Hello');
$history[] = $jan->buildMessage('assistant', $reply);

// Second turn (with history)
$response = $jan->chatWithHistory($history, 'Continue...');
$reply = $jan->extractMessageFromResponse($response);
$history[] = $jan->buildMessage('user', 'Continue...');
$history[] = $jan->buildMessage('assistant', $reply);
```

## Key Methods

| Method                                                 | Purpose                                 |
| ------------------------------------------------------ | --------------------------------------- |
| `chatWithHistory($history, $newMsg, $model, $options)` | Continue conversation with full history |
| `extractMessageFromResponse($response)`                | Get assistant's reply from response     |
| `buildMessage($role, $content)`                        | Create formatted message object         |

## Message Format

```php
[
    ['role' => 'system', 'content' => 'You are...'],      // Optional
    ['role' => 'user', 'content' => 'First question'],
    ['role' => 'assistant', 'content' => 'First answer'],
    ['role' => 'user', 'content' => 'Follow-up'],
    // ... send ALL of these with each request
]
```

## Storage Options

```php
// Session
session(['jan_conv' => $history]);

// Database
$conversation->messages()->createMany([...]);

// Cache
Cache::put("jan_{$userId}", $history, now()->addHours(24));
```

## Response Structure

```php
$response->json('choices.0.message.content')  // Assistant's reply
$response->json('usage')                       // Token usage
$response->json('id')                          // Completion ID (NOT session ID!)
```

## Common Patterns

### With System Prompt

```php
$history = [['role' => 'system', 'content' => 'You are a Laravel expert.']];
$response = $jan->chatWithHistory($history, 'How do I...?');
```

### With Options

```php
$response = $jan->chatWithHistory($history, 'Question', options: [
    'temperature' => 0.8,
    'max_tokens' => 2048,
]);
```

### Controller Example

```php
public function chat(Request $request, JanService $jan) {
    $history = session('jan_conv', []);
    $response = $jan->chatWithHistory($history, $request->input('message'));

    if ($response->successful()) {
        $reply = $jan->extractMessageFromResponse($response);
        $history[] = $jan->buildMessage('user', $request->input('message'));
        $history[] = $jan->buildMessage('assistant', $reply);
        session(['jan_conv' => $history]);

        return response()->json(['message' => $reply]);
    }
}
```

## Important Notes

- ❌ The `id` in response is NOT a conversation ID
- ✅ Send full history with EVERY request
- ✅ Store history in session/DB/cache yourself
- ✅ Limit history length for long conversations
- ✅ Always check `$response->successful()`

## Full Documentation

See `JAN_CONVERSATION_HISTORY.md` for complete guide with all examples.
