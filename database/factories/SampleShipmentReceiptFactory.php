<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SampleShipment;
use App\Models\SampleShipmentReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SampleShipmentReceipt> */
class SampleShipmentReceiptFactory extends Factory
{
    protected $model = SampleShipmentReceipt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shipment_id' => SampleShipment::factory(),
            'receiver_id' => User::factory(),
            'received_at' => now()->toDateString(),
            'quantity' => 1,
            'quantities' => ['total' => '1.00'],
            'condition' => 'good',
            'disposition' => SampleShipmentReceipt::DISPOSITION_STORED,
            'ownership' => 'receiver_office',
            'notes' => null,
        ];
    }
}
