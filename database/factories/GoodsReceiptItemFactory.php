<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoodsReceiptItem> */
final class GoodsReceiptItemFactory extends Factory
{
    protected $model = GoodsReceiptItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'quantity' => fake()->randomFloat(2, 1, 10),
        ];
    }
}
