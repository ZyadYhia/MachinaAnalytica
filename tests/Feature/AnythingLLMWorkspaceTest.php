<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('can create a new workspace', function () {
    Http::fake([
        '*/v1/workspace/new' => Http::response([
            'workspace' => [
                'id' => 1,
                'name' => 'New Workspace',
                'slug' => 'new-workspace',
            ],
            'message' => 'Workspace created successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.workspaces.create'), [
        'name' => 'New Workspace',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['workspace', 'message']);
});

test('workspace creation requires name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('anythingllm.workspaces.create'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can update a workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace/update' => Http::response([
            'workspace' => [
                'id' => 1,
                'name' => 'Updated Workspace',
                'slug' => 'test-workspace',
            ],
            'message' => 'Workspace updated successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(route('anythingllm.workspace.update', 'test-workspace'), [
        'name' => 'Updated Workspace',
        'openAiTemp' => 0.7,
        'topN' => 5,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['workspace', 'message']);
});

test('can delete a workspace', function () {
    Http::fake([
        '*/v1/workspace/test-workspace' => Http::response([
            'message' => 'Workspace deleted successfully',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson(route('anythingllm.workspace.delete', 'test-workspace'));

    $response->assertOk()
        ->assertJsonStructure(['message']);
});

test('workspace update validates temperature range', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(route('anythingllm.workspace.update', 'test-workspace'), [
        'openAiTemp' => 3.0, // Max is 2
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['openAiTemp']);
});

test('workspace update validates similarity threshold range', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(route('anythingllm.workspace.update', 'test-workspace'), [
        'similarityThreshold' => 1.5, // Max is 1
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['similarityThreshold']);
});
