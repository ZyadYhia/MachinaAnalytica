<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
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
            'model' => fake()->randomElement(['llama3-8b-instruct', 'gpt-4', 'claude-3-opus']),
            'title' => fake()->optional()->sentence(3),
            'summary' => fake()->optional()->paragraph(),
            'metadata' => [
                'total_messages' => fake()->numberBetween(1, 50),
                'total_tokens' => fake()->numberBetween(100, 10000),
            ],
            'last_message_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
