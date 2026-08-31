<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'vendor_id' => Vendor::factory(),
            'created_by_id' => User::factory(),
            'quotation_number' => strtoupper(fake()->unique()->bothify('QTN-######')),
            'quoted_at' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'IDR',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'subtotal_amount' => 0,
            'total_amount' => 0,
            'status' => Quotation::STATUS_SUBMITTED,
            'notes' => null,
            'submitted_at' => now(),
        ];
    }
}
