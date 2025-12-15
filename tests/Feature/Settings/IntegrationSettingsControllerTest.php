<?php

use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\LLM\LLMManager;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\patch;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('displays the integration settings page', function () {
    actingAs($this->user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('settings/integration')
                ->has('integration')
                ->has('availableProviders')
        );
});

it('shows null integration for users without settings', function () {
    actingAs($this->user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->where('integration', null)
        );
});

it('shows existing integration settings', function () {
    $integration = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
        'active_model' => 'llama3-8b',
        'chat_mode' => 'sync',
    ]);

    actingAs($this->user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->where('integration.active_integration', 'jan')
                ->where('integration.active_model', 'llama3-8b')
                ->where('integration.chat_mode', 'sync')
        );
});

it('can update integration settings', function () {
    actingAs($this->user)
        ->patch('/settings/integrations', [
            'active_integration' => 'jan',
            'active_model' => 'llama3-8b',
            'chat_mode' => 'async',
        ])
        ->assertOk();

    assertDatabaseHas('llm_integrations', [
        'user_id' => $this->user->id,
        'active_integration' => 'jan',
        'active_model' => 'llama3-8b',
        'chat_mode' => 'async',
    ]);
});

it('validates integration settings on update', function () {
    actingAs($this->user)
        ->patch('/settings/integrations', [
            'active_integration' => 'invalid-provider',
            'chat_mode' => 'async',
        ])
        ->assertSessionHasErrors(['active_integration']);
});

it('requires valid chat mode', function () {
    actingAs($this->user)
        ->patch('/settings/integrations', [
            'active_integration' => 'jan',
            'chat_mode' => 'invalid-mode',
        ])
        ->assertSessionHasErrors(['chat_mode']);
});

it('returns current integration settings via show endpoint', function () {
    $integration = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'anythingllm',
        'active_model' => 'workspace-1',
    ]);

    actingAs($this->user)
        ->get('/settings/integrations/show')
        ->assertOk()
        ->assertJson([
            'integration' => [
                'id' => $integration->id,
                'active_integration' => 'anythingllm',
                'active_model' => 'workspace-1',
            ],
        ]);
});

it('checks health of a specific provider', function () {
    $manager = mock(LLMManager::class);
    $manager->shouldReceive('checkProviderHealth')
        ->with('jan')
        ->andReturn(true);

    actingAs($this->user)
        ->get('/settings/integrations/health/jan')
        ->assertOk()
        ->assertJson([
            'provider' => 'jan',
            'status' => 'online',
            'healthy' => true,
        ]);
});

it('returns offline status for unhealthy provider', function () {
    $manager = mock(LLMManager::class);
    $manager->shouldReceive('checkProviderHealth')
        ->with('jan')
        ->andReturn(false);

    actingAs($this->user)
        ->get('/settings/integrations/health/jan')
        ->assertOk()
        ->assertJson([
            'provider' => 'jan',
            'status' => 'offline',
            'healthy' => false,
        ]);
});

it('checks health of users active integration', function () {
    LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
    ]);

    $manager = mock(LLMManager::class);
    $manager->shouldReceive('updateUserHealthStatus')
        ->with($this->user)
        ->andReturn(true);

    actingAs($this->user)
        ->get('/settings/integrations/health')
        ->assertOk()
        ->assertJson([
            'healthy' => true,
            'status' => 'online',
        ]);
});

it('returns error when checking health without active integration', function () {
    actingAs($this->user)
        ->get('/settings/integrations/health')
        ->assertStatus(400)
        ->assertJson([
            'message' => 'No active integration configured.',
            'healthy' => false,
        ]);
});

it('lists models for a specific provider', function () {
    $manager = mock(LLMManager::class);
    $manager->shouldReceive('getProviderModels')
        ->with('jan')
        ->andReturn([
            ['id' => 'model-1', 'name' => 'Model 1'],
            ['id' => 'model-2', 'name' => 'Model 2'],
        ]);

    actingAs($this->user)
        ->get('/settings/integrations/models/jan')
        ->assertOk()
        ->assertJson([
            'provider' => 'jan',
            'models' => [
                ['id' => 'model-1', 'name' => 'Model 1'],
                ['id' => 'model-2', 'name' => 'Model 2'],
            ],
        ]);
});

it('lists models for users active integration', function () {
    LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
    ]);

    $manager = mock(LLMManager::class);
    $manager->shouldReceive('getProviderModels')
        ->with('jan')
        ->andReturn([
            ['id' => 'model-1', 'name' => 'Model 1'],
        ]);

    actingAs($this->user)
        ->get('/settings/integrations/models')
        ->assertOk()
        ->assertJson([
            'provider' => 'jan',
            'models' => [
                ['id' => 'model-1', 'name' => 'Model 1'],
            ],
        ]);
});

it('requires authentication for all endpoints', function () {
    get('/settings/integrations')->assertRedirect('/login');
    patch('/settings/integrations')->assertRedirect('/login');
    get('/settings/integrations/show')->assertRedirect('/login');
    get('/settings/integrations/health')->assertRedirect('/login');
    get('/settings/integrations/health/jan')->assertRedirect('/login');
    get('/settings/integrations/models')->assertRedirect('/login');
    get('/settings/integrations/models/jan')->assertRedirect('/login');
});
