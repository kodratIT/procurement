<?php

namespace Database\Factories;

use App\Models\ProcurementItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    protected $model = PurchaseRequestItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 500);
        $unitPrice = fake()->randomFloat(2, 1000, 5000000);

        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'procurement_item_id' => ProcurementItem::factory(),
            'procurement_unit_id' => null,
            'procurement_variant_id' => null,
            'item_name' => fake()->words(3, true),
            'unit_name' => 'pcs',
            'variant_name' => null,
            'variant_value' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseRequestItem $line): void {
            if ($line->procurement_unit_id === null) {
                $line->procurement_unit_id = $line->procurementItem?->unit_id;
            }
        });
    }
}
