<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\SampleShipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SampleShipment> */
class SampleShipmentFactory extends Factory
{
    protected $model = SampleShipment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shipment_number' => null,
            'purchase_order_id' => PurchaseOrder::factory(),
            'office_id' => Office::factory(),
            'sender_office_id' => fn (array $attributes): mixed => $attributes['office_id'],
            'receiver_office_id' => Office::factory(),
            'sender_id' => User::factory(),
            'receiver_id' => null,
            'cost_center_id' => null,
            'purpose' => fake()->sentence(6),
            'requested_at' => now()->toDateString(),
            'planned_ship_date' => now()->addDay()->toDateString(),
            'shipped_at' => null,
            'received_at' => null,
            'confirmed_at' => null,
            'returned_at' => null,
            'completed_at' => null,
            'tracking_no' => null,
            'shipping_cost' => 0,
            'currency' => 'IDR',
            'approval_route' => SampleShipment::APPROVAL_ROUTE_PROCUREMENT,
            'condition' => 'good',
            'ownership' => 'sender_office',
            'status' => SampleShipment::STATUS_DRAFT,
            'notes' => null,
        ];
    }
}
