<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompressorAirBlower>
 */
class CompressorAirBlowerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $flow = fake()->randomFloat(2, 130, 170);
        $temperature = fake()->randomFloat(2, 65, 85);
        $pressure = fake()->randomFloat(2, 85, 110);
        $vibration = fake()->randomFloat(2, 0.3, 0.7);

        // Determine status based on values
        $status = 'normal';
        if ($temperature > 80 || $pressure > 105 || $vibration > 0.65) {
            $status = 'warning';
        }
        if ($temperature > 83 || $pressure > 108 || $vibration > 0.68) {
            $status = 'critical';
        }

        return [
            'flow' => $flow,
            'temperature' => $temperature,
            'pressure' => $pressure,
            'vibration' => $vibration,
            'status' => $status,
        ];
    }

    /**
     * Indicate that the reading is in warning state.
     */
    public function warning(): static
    {
        return $this->state(fn(array $attributes) => [
            'temperature' => fake()->randomFloat(2, 80, 85),
            'pressure' => fake()->randomFloat(2, 105, 110),
            'vibration' => fake()->randomFloat(2, 0.65, 0.8),
            'status' => 'warning',
        ]);
    }

    /**
     * Indicate that the reading is in critical state.
     */
    public function critical(): static
    {
        return $this->state(fn(array $attributes) => [
            'temperature' => fake()->randomFloat(2, 85, 95),
            'pressure' => fake()->randomFloat(2, 110, 120),
            'vibration' => fake()->randomFloat(2, 0.8, 1.2),
            'status' => 'critical',
        ]);
    }
}
