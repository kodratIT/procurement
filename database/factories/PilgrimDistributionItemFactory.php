<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DistributionItem;
use App\Models\Pilgrim;
use App\Models\PilgrimDistributionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PilgrimDistributionItem> */
final class PilgrimDistributionItemFactory extends Factory
{
    protected $model = PilgrimDistributionItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'distribution_item_id' => DistributionItem::factory(),
            'pilgrim_id' => Pilgrim::factory(),
            'quantity' => '1.00',
            'status' => PilgrimDistributionItem::STATUS_PENDING,
        ];
    }
}
