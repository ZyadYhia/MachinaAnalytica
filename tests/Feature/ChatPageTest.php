<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('chat page loads with workspaces from anythingllm', function () {
    Http::fake([
        '*/api/v1/workspaces' => Http::response([
            'workspaces' => [
                ['id' => 1, 'name' => 'machina', 'slug' => 'machina'],
                ['id' => 3, 'name' => 'MachinaAnaltica', 'slug' => 'machinaanaltica'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/chat');

    $response->assertOk();
});

test('chat page handles anythingllm connection failure gracefully', function () {
    Http::fake([
        '*/api/v1/workspaces' => Http::response([], 500),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/chat');

    $response->assertOk();
});
