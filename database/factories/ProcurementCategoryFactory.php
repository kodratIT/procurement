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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CAT-????')),
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active' => true,
        ];
    }
}
