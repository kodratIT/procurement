<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = strtoupper(fake()->unique()->bothify('INV-######'));

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'vendor_id' => fn (array $attributes): int => (int) PurchaseOrder::query()->find($attributes['purchase_order_id'])?->vendor_id,
            'recorded_by_id' => User::factory(),
            'office_id' => fn (array $attributes): int => (int) PurchaseOrder::query()->find($attributes['purchase_order_id'])?->office_id,
            'branch_id' => null,
            'department_id' => null,
            'currency' => 'IDR',
            'invoice_number' => $number,
            'normalized_invoice_number' => mb_strtoupper($number),
            'total_amount' => '100.00',
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_UNPAID,
            'match_status' => Invoice::MATCH_STATUS_MATCHED,
            'review_status' => Invoice::REVIEW_STATUS_PENDING,
            'mismatch_reason' => null,
            'matched_at' => now(),
            'approved_at' => null,
            'notes' => null,
        ];
    }
}
