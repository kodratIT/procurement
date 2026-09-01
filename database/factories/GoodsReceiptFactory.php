<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoodsReceipt> */
final class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $order = PurchaseOrder::factory();

        return [
            'purchase_order_id' => $order,
            'received_date' => now()->toDateString(),
            'receiver_id' => User::factory(),
            'office_id' => Office::factory(),
            'branch_id' => null,
            'department_id' => null,
            'status' => GoodsReceipt::STATUS_PARTIALLY_RECEIVED,
            'correction_of_id' => null,
            'correction_reason' => null,
            'notes' => null,
        ];
    }
}
