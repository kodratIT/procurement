<?php

namespace Database\Factories;

use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationItem> */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 100, 5000000);

        return [
            'quotation_id' => Quotation::factory(),
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'description' => null,
            'specifications' => null,
            'quantity' => $quantity,
            'unit_name' => 'pcs',
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'total_price' => round($quantity * $unitPrice, 2),
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (QuotationItem $item): void {
            $quotation = Quotation::query()->withoutGlobalScopes()->find($item->quotation_id);
            if ($quotation === null) {
                return;
            }

            $requestItem = PurchaseRequestItem::query()
                ->where('purchase_request_id', $quotation->purchase_request_id)
                ->first();

            if ($requestItem === null) {
                $requestItem = PurchaseRequestItem::factory()->create([
                    'purchase_request_id' => $quotation->purchase_request_id,
                ]);
            }

            $item->purchase_request_item_id = $requestItem->getKey();
        });
    }
}
