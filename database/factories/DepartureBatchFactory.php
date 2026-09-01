<?php

namespace Database\Factories;

use App\Models\DepartureBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for the base branch (d4eaf11) where departure_batches has
 * no office_id column yet (F3.4 adds it separately).
 *
 * @extends Factory<DepartureBatch>
 */
class DepartureBatchFactory extends Factory
{
    protected $model = DepartureBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'UMR-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->sentence(3),
            'departure_date' => fake()->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'return_date' => null,
            'capacity' => fake()->numberBetween(20, 100),
            'status' => 'planned',
            'is_active' => true,
        ];
    }
}
