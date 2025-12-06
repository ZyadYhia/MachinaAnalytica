<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LLMIntegration>
 */
class LLMIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'active_integration' => fake()->randomElement(['jan', 'anythingllm', 'none']),
            'integration_status' => fake()->randomElement(['online', 'offline']),
            'active_model' => fake()->randomElement(['llama3-8b-instruct', 'janhq/Jan-v2-VL-high', null]),
            'model_provider' => fake()->randomElement(['jan', 'anythingllm', null]),
            'chat_mode' => fake()->randomElement(['sync', 'async']),
            'last_health_check_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'provider_config' => [
                'temperature' => fake()->randomFloat(1, 0, 1),
                'max_tokens' => fake()->numberBetween(1000, 4000),
            ],
        ];
    }

    public function online(): static
    {
        return $this->state(fn(array $attributes) => [
            'integration_status' => 'online',
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn(array $attributes) => [
            'integration_status' => 'offline',
        ]);
    }

    public function jan(): static
    {
        return $this->state(fn(array $attributes) => [
            'active_integration' => 'jan',
            'model_provider' => 'jan',
            'active_model' => 'llama3-8b-instruct',
        ]);
    }

    public function anythingllm(): static
    {
        return $this->state(fn(array $attributes) => [
            'active_integration' => 'anythingllm',
            'model_provider' => 'anythingllm',
            'active_model' => 'gpt-4',
        ]);
    }

    public function sync(): static
    {
        return $this->state(fn(array $attributes) => [
            'chat_mode' => 'sync',
        ]);
    }

    public function async(): static
    {
        return $this->state(fn(array $attributes) => [
            'chat_mode' => 'async',
        ]);
    }
}
