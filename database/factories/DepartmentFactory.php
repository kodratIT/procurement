<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'branch_id' => null,
            'code' => strtoupper(fake()->unique()->lexify('DEP-???')),
            'name' => fake()->unique()->jobTitle(),
            'is_active' => true,
            'disabled_at' => null,
        ];
    }
}
