<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guest cannot access chat page', function () {
    $response = $this->get(route('chat.index'));

    $response->assertRedirect(route('login'));
});

test('can send chat message with anythingllm integration', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [
                ['name' => 'Test Workspace', 'slug' => 'test-workspace'],
            ],
        ], 200),
        '*/v1/workspace/test-workspace/chat' => Http::response([
            'textResponse' => 'This is a test AI response',
            'type' => 'chat',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => 'Hello AI',
        'workspace' => 'test-workspace',
        'mode' => 'chat',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'timestamp', 'workspace', 'type'])
        ->assertJson([
            'message' => 'This is a test AI response',
            'workspace' => 'test-workspace',
            'type' => 'chat',
        ]);
});

test('chat message uses default workspace when not provided', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [
                ['name' => 'Default Workspace', 'slug' => 'default-workspace'],
            ],
        ], 200),
        '*/v1/workspace/default-workspace/chat' => Http::response([
            'textResponse' => 'Response from default workspace',
            'type' => 'chat',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => 'Hello',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Response from default workspace',
            'workspace' => 'default-workspace',
        ]);
});

test('chat returns error when no workspace is available', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => 'Hello',
    ]);

    $response->assertStatus(503)
        ->assertJsonStructure(['error', 'message']);
});

test('chat returns error when anythingllm service fails', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [
                ['name' => 'Test Workspace', 'slug' => 'test-workspace'],
            ],
        ], 200),
        '*/v1/workspace/test-workspace/chat' => Http::response([
            'error' => 'Service unavailable',
        ], 503),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => 'Hello',
        'workspace' => 'test-workspace',
    ]);

    $response->assertStatus(503)
        ->assertJsonStructure(['error', 'message']);
});

test('chat message validation requires message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'workspace' => 'test-workspace',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat message validation enforces max length', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => str_repeat('a', 5001),
        'workspace' => 'test-workspace',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat mode validation only allows chat or query', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [
                ['name' => 'Test Workspace', 'slug' => 'test-workspace'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('chat.send'), [
        'message' => 'Hello',
        'workspace' => 'test-workspace',
        'mode' => 'invalid-mode',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);
});
