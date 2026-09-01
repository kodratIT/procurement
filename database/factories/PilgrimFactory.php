<?php

namespace Database\Factories;

use App\Models\Pilgrim;
use App\Models\UmrahBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pilgrim> */
class PilgrimFactory extends Factory
{
    protected $model = Pilgrim::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'office_id' => null,
            'umrah_batch_id' => UmrahBatch::factory(),
            'name' => fake()->name(),
            'passport_no' => 'P'.fake()->unique()->numerify('########'),
            'phone' => fake()->numerify('08##########'),
            'status' => Pilgrim::STATUS_REGISTERED,
            'is_active' => true,
            'disabled_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Pilgrim $pilgrim): void {
            if ($pilgrim->office_id !== null || ! is_numeric($pilgrim->umrah_batch_id)) {
                return;
            }

            $pilgrim->office_id = UmrahBatch::query()
                ->withoutGlobalScopes()
                ->findOrFail($pilgrim->umrah_batch_id)
                ->office_id;
        });
    }

    public function forBatch(UmrahBatch $batch): static
    {
        return $this->state([
            'office_id' => $batch->office_id,
            'umrah_batch_id' => $batch->getKey(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => Pilgrim::STATUS_CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => Pilgrim::STATUS_CANCELLED,
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
