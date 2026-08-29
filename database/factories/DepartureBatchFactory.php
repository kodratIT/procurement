<?php

namespace Database\Factories;

use App\Models\DepartureBatch;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartureBatch>
 */
class DepartureBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'UMR-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->sentence(3),
            'office_id' => Office::factory(),
            'departure_date' => fake()->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'return_date' => null,
            'capacity' => fake()->numberBetween(20, 100),
            'pax_count' => fake()->numberBetween(1, 60),
            'status' => 'planned',
            'is_active' => true,
        ];
    }
}
