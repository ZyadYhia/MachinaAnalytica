<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guest cannot access anythingllm endpoints', function () {
    $response = $this->get(route('anythingllm.workspaces'));

    $response->assertRedirect(route('login'));
});

test('can list all workspaces', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response([
            'workspaces' => [
                ['name' => 'Workspace 1', 'slug' => 'workspace-1'],
                ['name' => 'Workspace 2', 'slug' => 'workspace-2'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.workspaces'));

    $response->assertOk()
        ->assertJsonStructure(['workspaces']);
});

test('can get a specific workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace' => Http::response([
            'workspace' => [
                'name' => 'Test Workspace',
                'slug' => 'test-workspace',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.workspace', 'test-workspace'));

    $response->assertOk()
        ->assertJsonStructure(['workspace']);
});

test('can send chat message to workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/chat' => Http::response([
            'textResponse' => 'This is a test response',
            'type' => 'chat',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.chat'), [
        'workspace_slug' => 'test-workspace',
        'message' => 'Hello, how are you?',
        'mode' => 'chat',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['textResponse', 'type']);
});

test('chat message validation fails with missing message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.chat'), [
        'workspace_slug' => 'test-workspace',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat message validation fails with missing workspace slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.chat'), [
        'message' => 'Hello',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['workspace_slug']);
});

test('chat message validation fails with invalid mode', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.chat'), [
        'workspace_slug' => 'test-workspace',
        'message' => 'Hello',
        'mode' => 'invalid',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);
});

test('can perform vector search in workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/vector-search' => Http::response([
            'results' => [
                ['text' => 'Result 1', 'score' => 0.95],
                ['text' => 'Result 2', 'score' => 0.87],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.vector-search', 'test-workspace'), [
        'query' => 'test query',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['results']);
});

test('can list chats in workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/chats' => Http::response([
            'chats' => [
                ['id' => 1, 'message' => 'Hello'],
                ['id' => 2, 'message' => 'How are you?'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.chats', 'test-workspace'));

    $response->assertOk()
        ->assertJsonStructure(['chats']);
});

test('can list all documents', function () {
    Http::fake([
        '*/v1/documents' => Http::response([
            'documents' => [
                ['name' => 'document1.pdf', 'location' => 'folder1'],
                ['name' => 'document2.txt', 'location' => 'folder2'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.documents'));

    $response->assertOk()
        ->assertJsonStructure(['documents']);
});

test('can check authentication status', function () {
    Http::fake([
        '*/v1/auth' => Http::response(['authenticated' => true], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.check-auth'));

    $response->assertOk()
        ->assertJson(['authenticated' => true]);
});

test('handles failed api requests gracefully', function () {
    Http::fake([
        '*/v1/workspaces' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('anythingllm.workspaces'));

    $response->assertStatus(401)
        ->assertJsonStructure(['error', 'message']);
});
