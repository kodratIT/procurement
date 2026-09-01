<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'recorded_by_id' => User::factory(),
            'amount' => '50.00',
            'payment_date' => now()->toDateString(),
            'reference_number' => strtoupper(fake()->unique()->bothify('PAY-######')),
            'notes' => null,
        ];
    }
}
