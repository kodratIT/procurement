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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('UNT-??')),
            'name' => fake()->word(),
            'symbol' => null,
            'is_active' => true,
        ];
    }
}
