<?php

namespace Database\Factories;

use App\Models\ProcurementItem;
use App\Models\Vendor;
use App\Models\VendorItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorItem>
 */
class VendorItemFactory extends Factory
{
    protected $model = VendorItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'item_id' => ProcurementItem::factory(),
            'reference_price' => fake()->numberBetween(10000, 1000000),
            'currency' => 'IDR',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
