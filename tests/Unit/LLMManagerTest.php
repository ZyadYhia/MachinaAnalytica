<?php

use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\LLM\LLMManager;
use App\Services\LLM\Providers\AnythingLLMProvider;
use App\Services\LLM\Providers\JanProvider;

beforeEach(function () {
    $this->manager = app(LLMManager::class);
    $this->user = User::factory()->create();
});

it('returns list of available providers', function () {
    $providers = $this->manager->getAvailableProviders();

    expect($providers)->toBeArray()
        ->toContain('jan', 'anythingllm');
});

it('gets a provider instance by name', function () {
    $janProvider = $this->manager->provider('jan');
    $anythingProvider = $this->manager->provider('anythingllm');

    expect($janProvider)->toBeInstanceOf(JanProvider::class)
        ->and($anythingProvider)->toBeInstanceOf(AnythingLLMProvider::class);
});

it('throws exception for unknown provider', function () {
    $this->manager->provider('unknown-provider');
})->throws(\InvalidArgumentException::class, 'Unknown LLM provider: unknown-provider');

it('gets users active provider', function () {
    LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
    ]);

    $provider = $this->manager->getUserProvider($this->user);

    expect($provider)->toBeInstanceOf(JanProvider::class);
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
    $janHealth = $this->manager->checkProviderHealth('jan');

    expect($janHealth)->toBeBool();
});

it('returns false for health check on exception', function () {
    $health = $this->manager->checkProviderHealth('invalid-provider');

    expect($health)->toBeFalse();
});

it('updates user health status', function () {
    $integration = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
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
        provider: 'jan',
        model: 'llama3-8b',
        chatMode: 'sync'
    );

    expect($integration)->toBeInstanceOf(LLMIntegration::class)
        ->and($integration->active_integration)->toBe('jan')
        ->and($integration->active_model)->toBe('llama3-8b')
        ->and($integration->chat_mode)->toBe('sync')
        ->and($integration->user_id)->toBe($this->user->id);
});

it('updates existing user integration', function () {
    $existing = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
        'active_model' => 'old-model',
    ]);

    $updated = $this->manager->updateUserIntegration(
        user: $this->user,
        provider: 'anythingllm',
        model: 'new-model',
        chatMode: 'async'
    );

    expect($updated->id)->toBe($existing->id)
        ->and($updated->active_integration)->toBe('anythingllm')
        ->and($updated->active_model)->toBe('new-model')
        ->and($updated->chat_mode)->toBe('async');
});

it('lists models for a provider', function () {
    $models = $this->manager->listModels('jan');

    expect($models)->toBeArray();
});

it('returns empty array for models on exception', function () {
    $models = $this->manager->listModels('invalid-provider');

    expect($models)->toBeArray()
        ->toBeEmpty();
});
