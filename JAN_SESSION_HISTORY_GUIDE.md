# Jan Session-Based Conversation History

## Overview

This implementation stores conversation history in the session and sends all previous messages in each request to maintain context. This is a simple, reliable approach that doesn't depend on server-side caching.

## How It Works

```
1. User sends first message with optional system prompt
   → Laravel stores: [system, user, assistant] in session

2. User sends second message with same conversation_id
   → Laravel retrieves history from session
   → Laravel adds new user message to history
   → Sends ALL messages to Jan API
   → Stores updated history (including assistant response)

3. Repeat for subsequent messages
   → Full conversation context maintained automatically
```

## API Endpoints

### POST /jan/chat/history

Send a message and maintain conversation history.

**Request:**

```json
{
    "message": "Your message here",
    "conversation_id": "optional-conversation-id",
    "system_prompt": "Optional system prompt",
    "model": "llama3-8b-instruct",
    "temperature": 0.8,
    "max_tokens": 2048
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": "chatcmpl-123",
        "conversation_id": "user-123-chat",
        "message": "Assistant's response",
        "model": "llama3-8b-instruct",
        "usage": {
            "prompt_tokens": 150,
            "completion_tokens": 50,
            "total_tokens": 200
        },
        "history_length": 5
    }
}
```

### GET /jan/conversation/{conversationId?}

Get the full conversation history.

**Response:**

```json
{
    "success": true,
    "conversation_id": "user-123-chat",
    "messages": [
        { "role": "system", "content": "You are a helpful assistant." },
        { "role": "user", "content": "Hello!" },
        { "role": "assistant", "content": "Hi there!" },
        { "role": "user", "content": "Tell me about AI." },
        { "role": "assistant", "content": "AI is..." }
    ],
    "message_count": 5
}
```

### DELETE /jan/conversation/{conversationId?}

Clear the conversation history.

**Response:**

```json
{
    "success": true,
    "message": "Conversation 'user-123-chat' cleared successfully"
}
```

## Usage Examples

### JavaScript (Fetch API)

```javascript
// Start a new conversation
async function startConversation() {
    const response = await fetch('/jan/chat/history', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: 'Bearer ' + token,
        },
        body: JSON.stringify({
            message: 'Hello!',
            system_prompt: 'You are a helpful assistant.',
            conversation_id: 'user-123-chat',
            model: 'llama3-8b-instruct',
        }),
    });

    const data = await response.json();
    console.log('Response:', data.data.message);
    console.log('History length:', data.data.history_length);
}

// Continue the conversation
async function continueConversation() {
    const response = await fetch('/jan/chat/history', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: 'Bearer ' + token,
        },
        body: JSON.stringify({
            message: 'Tell me more about AI.',
            conversation_id: 'user-123-chat', // Same ID
        }),
    });

    const data = await response.json();
    console.log('Response:', data.data.message);
}

// Get full history
async function getHistory() {
    const response = await fetch('/jan/conversation/user-123-chat', {
        headers: { Authorization: 'Bearer ' + token },
    });

    const data = await response.json();
    console.log('Messages:', data.messages);
}

// Clear conversation
async function clearConversation() {
    await fetch('/jan/conversation/user-123-chat', {
        method: 'DELETE',
        headers: { Authorization: 'Bearer ' + token },
    });
}
```

### PHP (Laravel)

```php
use Illuminate\Support\Facades\Http;

// Start conversation
$response = Http::withToken($token)
    ->post('http://localhost:8000/jan/chat/history', [
        'message' => 'Hello!',
        'system_prompt' => 'You are a helpful assistant.',
        'conversation_id' => 'user-123-chat',
        'model' => 'llama3-8b-instruct',
    ]);

$data = $response->json();
echo "Response: {$data['data']['message']}\n";

// Continue conversation
$response = Http::withToken($token)
    ->post('http://localhost:8000/jan/chat/history', [
        'message' => 'Tell me more.',
        'conversation_id' => 'user-123-chat',
    ]);

// Get history
$history = Http::withToken($token)
    ->get('http://localhost:8000/jan/conversation/user-123-chat');

print_r($history->json()['messages']);

// Clear conversation
Http::withToken($token)
    ->delete('http://localhost:8000/jan/conversation/user-123-chat');
```

### cURL

```bash
# Start conversation
curl -X POST http://localhost:8000/jan/chat/history \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "message": "Hello!",
    "system_prompt": "You are a helpful assistant.",
    "conversation_id": "user-123-chat",
    "model": "llama3-8b-instruct"
  }'

# Continue conversation
curl -X POST http://localhost:8000/jan/chat/history \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "message": "Tell me more.",
    "conversation_id": "user-123-chat"
  }'

# Get history
curl http://localhost:8000/jan/conversation/user-123-chat \
  -H "Authorization: Bearer YOUR_TOKEN"

# Clear conversation
curl -X DELETE http://localhost:8000/jan/conversation/user-123-chat \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Session Storage Structure

The conversation is stored in the session like this:

```php
session()->get('jan_conversations'); // Returns:
[
    'user-123-chat' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Hello!'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
        ['role' => 'user', 'content' => 'Tell me about AI.'],
        ['role' => 'assistant', 'content' => 'AI is...'],
    ],
    'another-chat' => [
        ['role' => 'user', 'content' => 'Different conversation'],
        ['role' => 'assistant', 'content' => 'Yes, this is separate'],
    ],
]
```

## Features

### ✅ Multiple Conversations Per User

Each user can have multiple independent conversations by using different `conversation_id` values.

```javascript
// Chat 1
fetch('/jan/chat/history', {
    body: JSON.stringify({
        message: 'Question about Laravel',
        conversation_id: 'laravel-help',
    }),
});

// Chat 2 (completely independent)
fetch('/jan/chat/history', {
    body: JSON.stringify({
        message: 'Question about AI',
        conversation_id: 'ai-discussion',
    }),
});
```

### ✅ System Prompt Management

- System prompt is added once at the beginning
- Won't be duplicated if provided again with same conversation_id

### ✅ Automatic Context Maintenance

- All previous messages sent in each request
- Full conversation context always available
- No server-side state management needed

### ✅ Default Conversation

If you don't specify a `conversation_id`, it uses `'default'`:

```javascript
fetch('/jan/chat/history', {
    body: JSON.stringify({
        message: 'Hello',
        // conversation_id defaults to 'default'
    }),
});
```

## Benefits

| Feature        | Description                                       |
| -------------- | ------------------------------------------------- |
| **Simple**     | Just send messages, history handled automatically |
| **Reliable**   | No dependency on Jan server state                 |
| **Debuggable** | Can view full history via GET endpoint            |
| **Multi-User** | Session isolation per user                        |
| **Multi-Chat** | Multiple conversations per user                   |
| **No Expiry**  | Lasts as long as session (configurable)           |

## Limitations

| Limitation          | Workaround                          |
| ------------------- | ----------------------------------- | -------------------------------- |
| **Token Usage**     | All messages sent each time         | Store critical messages only     |
| **Session Size**    | Large conversations = large session | Clear old conversations          |
| **Session Timeout** | Lost when session expires           | Use database storage (see below) |

## Advanced: Database Storage

For production, you may want to store conversations in the database instead of sessions:

```php
// Migration
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('conversation_id')->index();
    $table->json('messages');
    $table->timestamps();
    $table->unique(['user_id', 'conversation_id']);
});

// Model
class Conversation extends Model
{
    protected $fillable = ['user_id', 'conversation_id', 'messages'];
    protected $casts = ['messages' => 'array'];
}

// Update Controller
$conversation = Conversation::firstOrCreate([
    'user_id' => auth()->id(),
    'conversation_id' => $conversationId,
], ['messages' => []]);

$messages = $conversation->messages;
// ... add new messages ...
$conversation->update(['messages' => $messages]);
```

## Testing

Run tests:

```bash
php artisan test --filter=JanSessionConversationTest
```

All 9 tests validate:

- ✅ Starting new conversations
- ✅ Continuing existing conversations
- ✅ Getting conversation history
- ✅ Clearing conversations
- ✅ Default conversation ID
- ✅ System prompt handling
- ✅ Multiple conversations
- ✅ Validation

## Session Configuration

Configure session lifetime in `config/session.php`:

```php
'lifetime' => env('SESSION_LIFETIME', 120), // minutes
```

For long-running conversations, increase the lifetime:

```env
SESSION_LIFETIME=1440  # 24 hours
```

## Files

- **Controller:** `app/Http/Controllers/JanController.php`
    - `chatWithHistory()` - Send message with history
    - `getConversation()` - Get conversation history
    - `clearConversation()` - Clear conversation

- **Routes:** `routes/web.php`
    - `POST /jan/chat/history`
    - `GET /jan/conversation/{id}`
    - `DELETE /jan/conversation/{id}`

- **Request:** `app/Http/Requests/SendJanPromptRequest.php`
    - Validates `message`, `conversation_id`, `system_prompt`

- **Tests:** `tests/Feature/JanSessionConversationTest.php`
    - 9 comprehensive tests

- **Example:** `examples/jan-session-conversation.php`
    - Complete usage examples

## Summary

This implementation provides a simple, reliable way to maintain conversation context with Jan:

1. **Send** message + conversation_id
2. **Store** all messages in session
3. **Retrieve** history from session
4. **Send** full history to Jan in next request
5. **Repeat** for natural conversations

No complex state management, no server-side dependencies - just works! 🚀
