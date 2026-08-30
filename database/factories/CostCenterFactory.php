<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'code' => strtoupper(fake()->unique()->lexify('CC-???')),
            'name' => fake()->unique()->company().' Cost Center',
            'is_active' => true,
            'disabled_at' => null,
        ];
    }
}
