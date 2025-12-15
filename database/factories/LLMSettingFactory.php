<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LLMSetting>
 */
class LLMSettingFactory extends Factory
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
            'provider' => fake()->randomElement(['jan', 'anythingllm']),
            'key' => fake()->randomElement(['api_url', 'auth_token', 'temperature', 'max_tokens']),
            'value' => fake()->word(),
            'is_encrypted' => fake()->boolean(30),
        ];
    }

    public function encrypted(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_encrypted' => true,
            'key' => 'auth_token',
        ]);
    }
}
