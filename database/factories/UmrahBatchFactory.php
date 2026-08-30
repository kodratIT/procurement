<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\UmrahBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UmrahBatch> */
class UmrahBatchFactory extends Factory
{
    protected $model = UmrahBatch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $departureDate = fake()->dateTimeBetween('+1 month', '+6 months');

        return [
            'office_id' => Office::factory(),
            'code' => 'UMR-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->sentence(3),
            'departure_date' => $departureDate->format('Y-m-d'),
            'return_date' => null,
            'capacity' => 50,
            'pilgrim_count' => 0,
            'status' => UmrahBatch::STATUS_PLANNED,
            'is_active' => true,
            'disabled_at' => null,
        ];
    }

    public function forOffice(Office $office): static
    {
        return $this->state(['office_id' => $office->getKey()]);
    }

    public function open(): static
    {
        return $this->state(['status' => UmrahBatch::STATUS_OPEN]);
    }

    public function closed(): static
    {
        return $this->state(['status' => UmrahBatch::STATUS_CLOSED]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
