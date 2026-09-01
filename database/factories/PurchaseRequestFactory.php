<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'requester_id' => User::factory(),
            'title' => fake()->sentence(4),
            'notes' => null,
            'reason' => fake()->sentence(8),
            'required_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'priority' => 'normal',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'total_amount' => 0,
        ];
    }
}
