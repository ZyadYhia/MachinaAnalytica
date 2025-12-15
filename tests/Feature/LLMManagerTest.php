<?php

use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\LLM\LLMManager;
use App\Services\LLM\Providers\AgentProvider;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->manager = app(LLMManager::class);
    $this->user = User::factory()->create();
});

it('returns list of available providers', function () {
    $providers = $this->manager->getAvailableProviders();

    expect($providers)->toBeArray()
        ->toContain('agent');
});

it('gets a provider instance by name', function () {
    $agentProvider = $this->manager->provider('agent');

    expect($agentProvider)->toBeInstanceOf(AgentProvider::class);
});

it('throws exception for unknown provider', function () {
    $this->manager->provider('unknown-provider');
})->throws(\InvalidArgumentException::class, "Provider 'unknown-provider' is not registered.");

it('gets users active provider', function () {
    LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'agent',
    ]);

    $provider = $this->manager->getUserProvider($this->user);

    expect($provider)->toBeInstanceOf(AgentProvider::class);
});

it('throws exception when user has no active provider', function () {
    $this->manager->getUserProvider($this->user);
})->throws(\RuntimeException::class, 'No active LLM integration configured for user.');

it('throws exception when user provider is set to none', function () {
    LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'none',
    ]);

    $this->manager->getUserProvider($this->user);
})->throws(\RuntimeException::class, 'No active LLM integration configured for user.');

it('checks provider health', function () {
    $agentHealth = $this->manager->checkProviderHealth('agent');

    expect($agentHealth)->toBeBool();
});

it('returns false for health check on exception', function () {
    $health = $this->manager->checkProviderHealth('invalid-provider');

    expect($health)->toBeFalse();
});

it('updates user health status', function () {
    $integration = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'agent',
        'integration_status' => 'offline',
    ]);

    $this->manager->updateUserHealthStatus($this->user);

    $integration->refresh();

    expect($integration->last_health_check_at)->not->toBeNull()
        ->and($integration->integration_status)->toBeIn(['online', 'offline']);
});

it('creates or updates user integration', function () {
    expect($this->user->llmIntegration)->toBeNull();

    $integration = $this->manager->updateUserIntegration(
        user: $this->user,
        provider: 'agent',
        model: 'agent-default',
        chatMode: 'sync'
    );

    expect($integration)->toBeInstanceOf(LLMIntegration::class)
        ->and($integration->active_integration)->toBe('agent')
        ->and($integration->active_model)->toBe('agent-default')
        ->and($integration->chat_mode)->toBe('sync')
        ->and($integration->user_id)->toBe($this->user->id);
});

it('updates existing user integration', function () {
    $existing = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'none',
        'active_model' => 'old-model',
    ]);

    $updated = $this->manager->updateUserIntegration(
        user: $this->user,
        provider: 'agent',
        model: 'new-model',
        chatMode: 'async'
    );

    expect($updated->id)->toBe($existing->id)
        ->and($updated->active_integration)->toBe('agent')
        ->and($updated->active_model)->toBe('new-model')
        ->and($updated->chat_mode)->toBe('async');
});

it('lists models for a provider', function () {
    $models = $this->manager->listModels('agent');

    expect($models)->toBeArray();
});

it('returns empty array for models on exception', function () {
    $models = $this->manager->listModels('invalid-provider');

    expect($models)->toBeArray()
        ->toBeEmpty();
});
