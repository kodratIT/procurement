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

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('ITM-??????')),
            'name' => fake()->unique()->words(3, true),
            'category_id' => ProcurementCategory::factory(),
            'unit_id' => ProcurementUnit::factory(),
            'description' => fake()->sentence(),
            'reference_price' => fake()->optional()->randomFloat(2, 1000, 5000000),
            'reference_currency' => 'IDR',
            'specifications' => null,
            'is_active' => true,
        ];
    }
}
