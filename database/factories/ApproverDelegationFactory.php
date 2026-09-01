<?php

namespace Database\Factories;

use App\Models\ApproverDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApproverDelegation>
 */
class ApproverDelegationFactory extends Factory
{
    protected $model = ApproverDelegation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delegator_id' => User::factory(),
            'delegate_id' => User::factory(),
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'reason' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
