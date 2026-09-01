<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
final class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'description' => fake()->sentence(3),
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'sort_order' => 0,
        ];
    }
}
