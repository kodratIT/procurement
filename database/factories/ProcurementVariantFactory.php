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
    protected $model = ProcurementVariant::class;

    public function definition(): array
    {
        return [
            'item_id' => ProcurementItem::factory(),
            'variation_type' => ProcurementVariant::TYPE_UKURAN,
            'code' => strtoupper(fake()->unique()->lexify('VAR-???')),
            'name' => 'Ukuran '.fake()->randomElement(['S', 'M', 'L', 'XL']),
            'value' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'is_active' => true,
        ];
    }
}
