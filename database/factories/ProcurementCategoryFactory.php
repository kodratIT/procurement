<?php

namespace Database\Factories;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementCategory>
 */
class ProcurementCategoryFactory extends Factory
{
    protected $model = ProcurementCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CAT-????')),
            'name' => fake()->words(2, true),
            'description' => null,
            'type' => ProcurementCategoryType::GOODS,
            'requires_batch' => false,
            'requires_jamaah' => false,
            'requires_vendor' => false,
            'requires_quotation' => false,
            'requires_receipt' => false,
            'requires_invoice' => false,
            'requires_po' => false,
            'workflow_reference' => null,
            'number_template' => null,
            'is_active' => true,
            'disabled_at' => null,
        ];
    }
}
