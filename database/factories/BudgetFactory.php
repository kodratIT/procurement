<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'cost_center_id' => CostCenter::factory(),
            'year' => (int) date('Y'),
            'amount' => '100000.00',
            'status' => Budget::STATUS_ACTIVE,
        ];
    }
}
