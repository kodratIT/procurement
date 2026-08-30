<?php

namespace Database\Factories;

use App\Models\ProcurementItem;
use App\Models\ProcurementVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementVariant>
 */
class ProcurementVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => ProcurementItem::factory(),
            'variation_type' => ProcurementVariant::TYPE_UKURAN,
            'code' => strtoupper(fake()->unique()->lexify('VAR-????')),
            'name' => fake()->word(),
            'value' => fake()->word(),
            'attributes' => null,
            'is_active' => true,
        ];
    }
}
