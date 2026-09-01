<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAssignment>
 */
class UserAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => null,
            'is_primary' => false,
            'is_active' => true,
            'disabled_at' => null,
        ];
    }
}
