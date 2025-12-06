<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => \App\Models\Conversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->paragraph(),
            'tool_calls' => null,
            'tool_results' => null,
            'metadata' => [
                'tokens' => fake()->numberBetween(10, 500),
                'processing_time_ms' => fake()->numberBetween(100, 3000),
            ],
        ];
    }

    public function userMessage(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'user',
        ]);
    }

    public function assistantMessage(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'assistant',
        ]);
    }

    public function withToolCalls(): static
    {
        return $this->state(fn(array $attributes) => [
            'tool_calls' => [
                [
                    'id' => fake()->uuid(),
                    'name' => fake()->word(),
                    'arguments' => ['query' => fake()->sentence()],
                ],
            ],
        ]);
    }
}
