<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can start a new conversation with history', function () {
    // Mock the Jan API response
    Http::fake([
        'localhost:1337/*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'llama3-8b-instruct',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hi there! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 10,
                'total_tokens' => 30,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_token' => 'test'])
        ->postJson('/jan/chat/history', [
            'message' => 'Hello!',
            'system_prompt' => 'You are a helpful assistant.',
            'conversation_id' => 'test-conversation',
            'model' => 'llama3-8b-instruct',
        ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'data' => [
            'conversation_id' => 'test-conversation',
            'history_length' => 3,
            'choices' => [
                [
                    'message' => [
                        'content' => 'Hi there! How can I help you today?',
                    ],
                ],
            ],
        ],
    ]);

    // Check that conversation was saved to session
    expect(session('jan_conversations.test-conversation'))
        ->toBeArray()
        ->toHaveCount(3); // system + user + assistant

    // Verify the messages in session
    $messages = session('jan_conversations.test-conversation');
    expect($messages[0]['role'])->toBe('system');
    expect($messages[0]['content'])->toBe('You are a helpful assistant.');
    expect($messages[1]['role'])->toBe('user');
    expect($messages[1]['content'])->toBe('Hello!');
    expect($messages[2]['role'])->toBe('assistant');
    expect($messages[2]['content'])->toBe('Hi there! How can I help you today?');
});

test('can continue conversation with existing history', function () {
    // Set up existing conversation in session
    session([
        'jan_conversations.test-conversation' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
        ],
    ]);

    // Mock the Jan API response
    Http::fake([
        'localhost:1337/*' => Http::response([
            'id' => 'chatcmpl-456',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'llama3-8b-instruct',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'AI stands for Artificial Intelligence...',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'message' => 'Tell me about AI',
            'conversation_id' => 'test-conversation',
        ]);

    $response->assertSuccessful();

    // Verify that request included all previous messages
    Http::assertSent(function ($request) {
        $data = $request->data();

        return count($data['messages']) === 4 // system + user + assistant + new user
            && $data['messages'][0]['role'] === 'system'
            && $data['messages'][1]['content'] === 'Hello!'
            && $data['messages'][2]['content'] === 'Hi there! How can I help?'
            && $data['messages'][3]['content'] === 'Tell me about AI';
    });

    // Check that conversation was updated in session
    expect(session('jan_conversations.test-conversation'))
        ->toHaveCount(5); // system + user + assistant + user + assistant
});

test('can get conversation history', function () {
    // Set up existing conversation in session
    session([
        'jan_conversations.my-chat' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/jan/conversation/my-chat');

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'conversation_id' => 'my-chat',
        'message_count' => 3,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ],
    ]);
});

test('returns empty history for non-existent conversation', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/jan/conversation/non-existent');

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'conversation_id' => 'non-existent',
        'message_count' => 0,
        'messages' => [],
    ]);
});

test('can clear conversation history', function () {
    // Set up existing conversation in session
    session([
        'jan_conversations.test-chat' => [
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ],
    ]);

    expect(session('jan_conversations.test-chat'))->toBeArray();

    $response = $this->actingAs($this->user)
        ->deleteJson('/jan/conversation/test-chat');

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'message' => "Conversation 'test-chat' cleared successfully",
    ]);

    // Verify conversation was cleared from session
    expect(session('jan_conversations.test-chat'))->toBeNull();
});

test('uses default conversation id when not provided', function () {
    Http::fake([
        'localhost:1337/*' => Http::response([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Hello!']],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'message' => 'Hi',
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.conversation_id', 'default');

    expect(session('jan_conversations.default'))->toBeArray();
});

test('system prompt is only added once to conversation', function () {
    // First message with system prompt
    session([
        'jan_conversations.test-chat' => [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ],
    ]);

    Http::fake([
        'localhost:1337/*' => Http::response([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Sure!']],
            ],
        ], 200),
    ]);

    // Second message with same system prompt
    $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'message' => 'Help me',
            'system_prompt' => 'You are helpful.',
            'conversation_id' => 'test-chat',
        ]);

    // Verify system prompt wasn't duplicated
    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'];
        $systemMessages = array_filter($messages, fn ($m) => $m['role'] === 'system');

        return count($systemMessages) === 1;
    });
});

test('can have multiple conversations for same user', function () {
    Http::fake([
        'localhost:1337/*' => Http::response([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Response']],
            ],
        ], 200),
    ]);

    // Start conversation 1
    $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'message' => 'Message 1',
            'conversation_id' => 'chat-1',
        ]);

    // Start conversation 2
    $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'message' => 'Message 2',
            'conversation_id' => 'chat-2',
        ]);

    // Verify both conversations exist separately
    expect(session('jan_conversations.chat-1'))->toBeArray();
    expect(session('jan_conversations.chat-2'))->toBeArray();
    expect(session('jan_conversations.chat-1'))->not->toEqual(session('jan_conversations.chat-2'));
});

test('validates required message or messages field', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/jan/chat/history', [
            'conversation_id' => 'test',
            // No message or messages provided
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('message');
});
