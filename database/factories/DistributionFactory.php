<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Distribution;
use App\Models\UmrahBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Distribution> */
final class DistributionFactory extends Factory
{
    protected $model = Distribution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'umrah_batch_id' => UmrahBatch::factory(),
            'distributed_at' => now()->toDateString(),
            'receipt_mode' => Distribution::RECEIPT_MODE_BATCH,
            'status' => Distribution::STATUS_RECORDED,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (): array => ['receipt_mode' => Distribution::RECEIPT_MODE_INDIVIDUAL]);
    }
}
