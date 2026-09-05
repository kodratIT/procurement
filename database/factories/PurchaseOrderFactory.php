<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po_number' => strtoupper(fake()->unique()->bothify('PO-####-####')),
            'purchase_request_id' => PurchaseRequest::factory(),
            'vendor_id' => Vendor::factory(),
            'office_id' => Office::factory(),
            'currency' => 'IDR',
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 0,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ];
    }
}
