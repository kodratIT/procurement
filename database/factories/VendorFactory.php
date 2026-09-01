<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('VND-#####')),
            'name' => fake()->company(),
            'vendor_type' => Vendor::TYPE_GOODS,
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('021-####-####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'tax_number' => fake()->numerify('##.###.###.#-###.###'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
