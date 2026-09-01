<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'code' => strtoupper(fake()->unique()->lexify('BR-???')),
            'name' => fake()->unique()->city().' Branch',
            'is_active' => true,
            'disabled_at' => null,
        ];
    }
}
