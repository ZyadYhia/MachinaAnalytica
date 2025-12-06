<?php

namespace App\Services\LLM;

use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\AnythingLLM\AnythingLLMService;
use App\Services\Jan\JanService;
use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\ChatResponse;
use App\Services\LLM\Providers\AnythingLLMProvider;
use App\Services\LLM\Providers\JanProvider;

class LLMManager
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerProviders();
    }

    /**
     * Register all available LLM providers
     */
    protected function registerProviders(): void
    {
        // Register Jan provider
        $this->providers['jan'] = function () {
            $janService = new JanService(
                baseUrl: config('services.jan.url'),
                authToken: config('services.jan.auth_token')
            );

            return new JanProvider($janService);
        };

        // Register AnythingLLM provider
        $this->providers['anythingllm'] = function () {
            $anythingLLMService = new AnythingLLMService(
                baseUrl: config('services.anythingllm.url'),
                authToken: config('services.anythingllm.auth_token')
            );

            return new AnythingLLMProvider($anythingLLMService);
        };
    }

    /**
     * Get provider instance by name
     */
    public function provider(string $name): LLMProviderInterface
    {
        if (! isset($this->providers[$name])) {
            throw new \InvalidArgumentException("Provider '{$name}' is not registered.");
        }

        return $this->providers[$name]();
    }

    /**
     * Get the active provider for a user
     */
    public function getUserProvider(User $user): LLMProviderInterface
    {
        $integration = $user->llmIntegration;

        if (! $integration || $integration->active_integration === 'none') {
            throw new \RuntimeException('No active LLM integration configured for user.');
        }

        return $this->provider($integration->active_integration);
    }

    /**
     * Send a chat request using the user's active provider
     */
    public function chat(User $user, ChatRequest $request): ChatResponse
    {
        $provider = $this->getUserProvider($user);

        return $provider->chat($request);
    }

    /**
     * Check health of a specific provider
     */
    public function checkProviderHealth(string $providerName): bool
    {
        try {
            $provider = $this->provider($providerName);

            return $provider->checkHealth();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of models from a specific provider
     */
    public function getProviderModels(string $providerName): array
    {
        try {
            $provider = $this->provider($providerName);

            return $provider->listModels();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all registered provider names
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Update user's integration settings
     */
    public function updateUserIntegration(
        User $user,
        string $provider,
        ?string $model = null,
        string $chatMode = 'sync'
    ): LLMIntegration {
        return $user->llmIntegration()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'active_integration' => $provider,
                'active_model' => $model,
                'model_provider' => $provider,
                'chat_mode' => $chatMode,
                'integration_status' => $this->checkProviderHealth($provider) ? 'online' : 'offline',
                'last_health_check_at' => now(),
            ]
        );
    }

    /**
     * Update health status for user's active integration
     */
    public function updateUserHealthStatus(User $user): bool
    {
        $integration = $user->llmIntegration;

        if (! $integration || $integration->active_integration === 'none') {
            return false;
        }

        $isHealthy = $this->checkProviderHealth($integration->active_integration);

        $integration->update([
            'integration_status' => $isHealthy ? 'online' : 'offline',
            'last_health_check_at' => now(),
        ]);

        return $isHealthy;
    }
}
