<?php

namespace App\Providers;

use App\Services\AnythingLLM\AnythingLLMService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAnythingLLM();
    }

    /**
     * Register AnythingLLM service.
     */
    protected function registerAnythingLLM(): void
    {
        $this->app->singleton(AnythingLLMService::class, function ($app) {
            return new AnythingLLMService(
                baseUrl: config('services.anythingllm.url'),
                authToken: config('services.anythingllm.auth_token')
            );
        });
    }
}
