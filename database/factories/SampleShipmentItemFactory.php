<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProcurementItem;
use App\Models\SampleShipment;
use App\Models\SampleShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SampleShipmentItem> */
class SampleShipmentItemFactory extends Factory
{
    protected $model = SampleShipmentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shipment_id' => SampleShipment::factory(),
            'purchase_order_item_id' => null,
            'procurement_item_id' => ProcurementItem::factory(),
            'procurement_variant_id' => null,
            'quantity' => fake()->randomFloat(2, 1, 10),
            'condition' => 'good',
            'ownership' => 'sender_office',
            'notes' => null,
        ];
    }
}
