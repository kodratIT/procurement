<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetReservation;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetReservation>
 */
class BudgetReservationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'purchase_request_id' => PurchaseRequest::factory(),
            'amount' => '1000.00',
            'status' => BudgetReservation::STATUS_RESERVED,
        ];
    }
}
