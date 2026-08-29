<?php

namespace Database\Factories;

use App\Models\ProcurementCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementCategory>
 */
class ProcurementCategoryFactory extends Factory
{
    protected $model = ProcurementCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CAT-????')),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
