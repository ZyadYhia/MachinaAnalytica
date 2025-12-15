<?php

use App\Services\Jan\JanService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Mock successful Jan API response
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is a test response from Jan API.',
                    ],
                ],
            ],
            'created' => time(),
            'id' => 'chatcmpl-'.uniqid(),
            'model' => 'Jan-v1-4B-Q4_K_M',
            'object' => 'chat.completion',
            'usage' => [
                'completion_tokens' => 50,
                'prompt_tokens' => 100,
                'total_tokens' => 150,
            ],
            'success' => true,
        ], 200),
    ]);
});

test('can send simple chat without history', function () {
    $jan = app(JanService::class);

    $response = $jan->chat('Hello, what is Laravel?');

    expect($response->successful())->toBeTrue()
        ->and($response->json('choices.0.message.content'))
        ->toBeString()
        ->toContain('This is a test response');
});

test('can chat with conversation history', function () {
    $jan = app(JanService::class);

    // Initial conversation history
    $history = [
        ['role' => 'user', 'content' => 'What is Laravel?'],
        ['role' => 'assistant', 'content' => 'Laravel is a PHP framework.'],
    ];

    $response = $jan->chatWithHistory($history, 'Tell me more about it');

    expect($response->successful())->toBeTrue();

    // Verify the request was made with the full history
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['messages'])
            && count($body['messages']) === 3 // 2 history + 1 new message
            && $body['messages'][0]['content'] === 'What is Laravel?'
            && $body['messages'][1]['content'] === 'Laravel is a PHP framework.'
            && $body['messages'][2]['content'] === 'Tell me more about it';
    });
});

test('can build message in correct format', function () {
    $jan = app(JanService::class);

    $message = $jan->buildMessage('user', 'Hello');

    expect($message)->toHaveKeys(['role', 'content'])
        ->and($message['role'])->toBe('user')
        ->and($message['content'])->toBe('Hello');
});

test('can extract message from response', function () {
    $jan = app(JanService::class);

    $response = $jan->chat('Test message');

    $extractedMessage = $jan->extractMessageFromResponse($response);

    expect($extractedMessage)
        ->toBeString()
        ->toBe('This is a test response from Jan API.');
});

test('extract message handles missing content gracefully', function () {
    // Use the mock response from beforeEach but test the extractMessageFromResponse logic
    $jan = app(JanService::class);

    // Test with a response object directly
    $mockResponse = new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['invalid' => 'structure']))
    );

    $extractedMessage = $jan->extractMessageFromResponse($mockResponse);

    expect($extractedMessage)->toBeNull();
});

test('can maintain multi-turn conversation', function () {
    $jan = app(JanService::class);

    // Start with empty history
    $history = [];

    // First turn
    $response1 = $jan->chat('What is PHP?');
    $reply1 = $jan->extractMessageFromResponse($response1);

    $history[] = $jan->buildMessage('user', 'What is PHP?');
    $history[] = $jan->buildMessage('assistant', $reply1);

    expect($history)->toHaveCount(2);

    // Second turn
    $response2 = $jan->chatWithHistory($history, 'What are its uses?');
    $reply2 = $jan->extractMessageFromResponse($response2);

    $history[] = $jan->buildMessage('user', 'What are its uses?');
    $history[] = $jan->buildMessage('assistant', $reply2);

    expect($history)->toHaveCount(4);

    // Third turn
    $response3 = $jan->chatWithHistory($history, 'Give me an example');
    $reply3 = $jan->extractMessageFromResponse($response3);

    $history[] = $jan->buildMessage('user', 'Give me an example');
    $history[] = $jan->buildMessage('assistant', $reply3);

    expect($history)->toHaveCount(6)
        ->and($history[0]['content'])->toBe('What is PHP?')
        ->and($history[2]['content'])->toBe('What are its uses?')
        ->and($history[4]['content'])->toBe('Give me an example');
});

test('includes system prompt in conversation history', function () {
    $jan = app(JanService::class);

    $history = [
        ['role' => 'system', 'content' => 'You are a helpful Laravel expert.'],
    ];

    $response = $jan->chatWithHistory($history, 'How do I create a model?');

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['messages'])
            && $body['messages'][0]['role'] === 'system'
            && $body['messages'][0]['content'] === 'You are a helpful Laravel expert.'
            && $body['messages'][1]['role'] === 'user'
            && $body['messages'][1]['content'] === 'How do I create a model?';
    });
});

test('can pass additional options with conversation history', function () {
    $jan = app(JanService::class);

    $history = [
        ['role' => 'user', 'content' => 'Hello'],
    ];

    $response = $jan->chatWithHistory(
        conversationHistory: $history,
        newMessage: 'Continue',
        options: [
            'temperature' => 0.9,
            'max_tokens' => 4096,
            'top_p' => 0.8,
        ]
    );

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['temperature'])
            && $body['temperature'] === 0.9
            && $body['max_tokens'] === 4096
            && $body['top_p'] === 0.8;
    });
});

test('conversation history order is preserved', function () {
    $jan = app(JanService::class);

    $history = [
        ['role' => 'user', 'content' => 'First message'],
        ['role' => 'assistant', 'content' => 'First response'],
        ['role' => 'user', 'content' => 'Second message'],
        ['role' => 'assistant', 'content' => 'Second response'],
    ];

    $response = $jan->chatWithHistory($history, 'Third message');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $messages = $body['messages'];

        return count($messages) === 5
            && $messages[0]['content'] === 'First message'
            && $messages[1]['content'] === 'First response'
            && $messages[2]['content'] === 'Second message'
            && $messages[3]['content'] === 'Second response'
            && $messages[4]['content'] === 'Third message';
    });
});

test('can extract id_slot from response', function () {
    // Create a mock response with id_slot using the Response class directly
    $jan = app(JanService::class);

    $mockResponse = new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Response',
                    ],
                ],
            ],
            'id_slot' => 42, // Include id_slot in response
            'usage' => [
                'total_tokens' => 100,
            ],
        ]))
    );

    $idSlot = $jan->extractIdSlot($mockResponse);

    expect($idSlot)->toBe(42);
});

test('returns null when id_slot is not in response', function () {
    $jan = app(JanService::class);

    // Using the default mock from beforeEach which doesn't have id_slot
    $response = $jan->chat('Test');

    $idSlot = $jan->extractIdSlot($response);

    expect($idSlot)->toBeNull();
});

test('can use id_slot in chatWithHistory', function () {
    $jan = app(JanService::class);

    $history = [
        ['role' => 'user', 'content' => 'Hello'],
    ];

    $response = $jan->chatWithHistory(
        conversationHistory: $history,
        newMessage: 'Continue',
        idSlot: 42
    );

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['id_slot'])
            && $body['id_slot'] === 42
            && isset($body['cache_prompt'])
            && $body['cache_prompt'] === true;
    });
});
