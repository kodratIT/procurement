<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\ProcurementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DistributionItem> */
final class DistributionItemFactory extends Factory
{
    protected $model = DistributionItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'distribution_id' => Distribution::factory(),
            'procurement_item_id' => ProcurementItem::factory(),
            'quantity' => '1.00',
        ];
    }
}
