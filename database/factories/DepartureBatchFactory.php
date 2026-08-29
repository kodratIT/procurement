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
    protected $model = DepartureBatch::class;

    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'code' => strtoupper(fake()->unique()->lexify('UMR-????')),
            'name' => fake()->sentence(2),
            'departure_date' => fake()->dateTimeBetween('+1 month', '+3 months')->format('Y-m-d'),
            'return_date' => null,
            'capacity' => fake()->numberBetween(20, 150),
            'status' => 'planned',
            'is_active' => true,
        ];
    }
}
