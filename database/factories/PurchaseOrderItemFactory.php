<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProcurementItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'procurement_item_id' => ProcurementItem::factory(),
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
            'unit_name' => 'pcs',
        ];
    }
}
