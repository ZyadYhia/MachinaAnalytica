<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\LLM\LLMManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationSettingsController extends Controller
{
    public function __construct(
        protected LLMManager $llmManager
    ) {}

    /**
     * Display the integration settings page
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $integration = $user->llmIntegration;

        return Inertia::render('settings/integration', [
            'integration' => $integration,
            'availableProviders' => $this->llmManager->getAvailableProviders(),
        ]);
    }

    /**
     * Get current integration settings
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $integration = $user->llmIntegration;

        return response()->json([
            'integration' => $integration,
            'availableProviders' => $this->llmManager->getAvailableProviders(),
        ]);
    }

    /**
     * Update integration settings
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'active_integration' => 'required|string|in:jan,anythingllm,none',
            'active_model' => 'nullable|string',
            'chat_mode' => 'required|string|in:sync,async',
            'provider_config' => 'nullable|array',
        ]);

        $user = $request->user();

        $integration = $this->llmManager->updateUserIntegration(
            user: $user,
            provider: $validated['active_integration'],
            model: $validated['active_model'] ?? null,
            chatMode: $validated['chat_mode']
        );

        // Update provider config if provided
        if (isset($validated['provider_config'])) {
            $integration->update([
                'provider_config' => $validated['provider_config'],
            ]);
        }

        return Inertia::render('settings/integration', [
            'integration' => $integration->fresh(),
            'availableProviders' => $this->llmManager->getAvailableProviders(),
        ]);
    }

    /**
     * Check health of a specific provider
     */
    public function checkHealth(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, ['jan', 'anythingllm'])) {
            return response()->json([
                'provider' => $provider,
                'status' => 'offline',
                'healthy' => false,
                'checked_at' => now()->toIso8601String(),
                'error' => 'Invalid provider',
            ], 400);
        }

        $isHealthy = $this->llmManager->checkProviderHealth($provider);

        return response()->json([
            'provider' => $provider,
            'status' => $isHealthy ? 'online' : 'offline',
            'healthy' => $isHealthy,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check health of user's active integration
     */
    public function checkUserHealth(Request $request): JsonResponse
    {
        $user = $request->user();
        $integration = $user->llmIntegration;

        if (! $integration || $integration->active_integration === 'none') {
            return response()->json([
                'message' => 'No active integration configured.',
                'status' => 'offline',
                'healthy' => false,
            ], 400);
        }

        $isHealthy = $this->llmManager->updateUserHealthStatus($user);

        return response()->json([
            'provider' => $integration->active_integration,
            'status' => $isHealthy ? 'online' : 'offline',
            'healthy' => $isHealthy,
            'checked_at' => now()->toIso8601String(),
            'integration' => $integration->fresh(),
        ]);
    }

    /**
     * Get available models from a specific provider
     */
    public function listModels(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, ['jan', 'anythingllm'])) {
            return response()->json([
                'provider' => $provider,
                'models' => [],
                'error' => 'Invalid provider',
            ], 400);
        }

        $models = $this->llmManager->getProviderModels($provider);

        return response()->json([
            'provider' => $provider,
            'models' => $models,
        ]);
    }

    /**
     * Get available models from user's active provider
     */
    public function listUserModels(Request $request): JsonResponse
    {
        $user = $request->user();
        $integration = $user->llmIntegration;

        if (! $integration || $integration->active_integration === 'none') {
            return response()->json([
                'message' => 'No active integration configured.',
                'models' => [],
            ], 400);
        }

        $models = $this->llmManager->getProviderModels($integration->active_integration);

        return response()->json([
            'provider' => $integration->active_integration,
            'models' => $models,
        ]);
    }
}
