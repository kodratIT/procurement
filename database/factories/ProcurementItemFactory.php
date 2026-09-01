<?php

namespace Database\Factories;

use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementItem>
 */
class ProcurementItemFactory extends Factory
{
    protected $model = ProcurementItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ProcurementCategory::factory(),
            'unit_id' => ProcurementUnit::factory(),
            'code' => strtoupper(fake()->unique()->lexify('ITM-????')),
            'name' => fake()->words(3, true),
            'description' => null,
            'is_active' => true,
        ];
    }
}
