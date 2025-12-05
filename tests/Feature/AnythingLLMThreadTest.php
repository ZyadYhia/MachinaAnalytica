<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('can create a thread in workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/new' => Http::response([
            'thread' => [
                'id' => 1,
                'name' => 'New Thread',
                'slug' => 'new-thread',
                'workspace_id' => 1,
            ],
            'message' => 'Thread created successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.threads.create', 'test-workspace'),
        ['name' => 'New Thread']
    );

    $response->assertCreated()
        ->assertJsonStructure(['thread', 'message']);
});

test('thread creation requires name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.threads.create', 'test-workspace'),
        []
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can update a thread', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/test-thread/update' => Http::response([
            'thread' => [
                'id' => 1,
                'name' => 'Updated Thread',
                'slug' => 'test-thread',
            ],
            'message' => 'Thread updated successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(
        route('anythingllm.thread.update', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread']),
        ['name' => 'Updated Thread']
    );

    $response->assertOk()
        ->assertJsonStructure(['thread', 'message']);
});

test('can delete a thread', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/test-thread' => Http::response([
            'message' => 'Thread deleted successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson(
        route('anythingllm.thread.delete', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread'])
    );

    $response->assertOk()
        ->assertJsonStructure(['message']);
});

test('can list chats in a thread', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/test-thread/chats' => Http::response([
            'chats' => [
                ['id' => 1, 'message' => 'Hello in thread', 'response' => 'Hi there!'],
                ['id' => 2, 'message' => 'How are you?', 'response' => 'I am good!'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(
        route('anythingllm.thread.chats', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread'])
    );

    $response->assertOk()
        ->assertJsonStructure(['chats']);
});

test('can send chat message to thread', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/test-thread/chat' => Http::response([
            'textResponse' => 'Response in thread',
            'type' => 'chat',
            'thread' => 'test-thread',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.thread.chat', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread']),
        [
            'message' => 'Hello in thread',
            'mode' => 'chat',
        ]
    );

    $response->assertOk()
        ->assertJsonStructure(['textResponse', 'type', 'thread']);
});

test('thread chat message requires message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.thread.chat', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread']),
        []
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('thread chat message validates mode', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.thread.chat', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread']),
        [
            'message' => 'Hello',
            'mode' => 'invalid',
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);
});

test('thread chat handles api failures gracefully', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/thread/test-thread/chat' => Http::response([
            'error' => 'Thread not found',
        ], 404),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('anythingllm.thread.chat', ['slug' => 'test-workspace', 'threadSlug' => 'test-thread']),
        ['message' => 'Hello']
    );

    $response->assertNotFound()
        ->assertJsonStructure(['error', 'message']);
});
