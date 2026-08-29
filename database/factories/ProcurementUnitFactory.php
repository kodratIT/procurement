<?php

namespace Database\Factories;

use App\Models\ProcurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementUnit>
 */
class ProcurementUnitFactory extends Factory
{
    protected $model = ProcurementUnit::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('UNT-???')),
            'name' => fake()->unique()->words(2, true),
            'symbol' => fake()->randomElement(['pcs', 'set', 'box', 'pkt']),
            'is_active' => true,
        ];
    }
}
