<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProcurementCategoryType;
use App\Enums\ProcurementFieldType;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\UmrahBatch;
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
            'requires_recommendation_reason' => false,
            'requires_recommendation_evidence' => false,
            'requires_receipt' => false,
            'requires_invoice' => false,
            'requires_po' => false,
            'workflow_reference' => null,
            'number_template' => null,
            'is_active' => true,
            'disabled_at' => null,
        ];
    }

    public function withSupplyFields(): static
    {
        return $this->afterCreating(function (ProcurementCategory $category): void {
            $batch = UmrahBatch::factory()->create();

            ProcurementField::create([
                'category_id' => $category->getKey(),
                'key' => 'size',
                'label' => 'Ukuran',
                'field_type' => ProcurementFieldType::Dropdown,
                'sort_order' => 10,
                'is_required' => true,
                'options' => ['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'],
                'default_value' => 'M',
            ]);
            ProcurementField::create([
                'category_id' => $category->getKey(),
                'key' => 'color',
                'label' => 'Warna',
                'field_type' => ProcurementFieldType::Dropdown,
                'sort_order' => 20,
                'is_required' => true,
                'options' => ['white' => 'Putih', 'black' => 'Hitam'],
                'visibility_conditions' => [
                    ['field' => 'size', 'operator' => 'is_not_empty'],
                ],
            ]);
            ProcurementField::create([
                'category_id' => $category->getKey(),
                'key' => 'pilgrim_count',
                'label' => 'Jumlah jamaah',
                'field_type' => ProcurementFieldType::Number,
                'sort_order' => 30,
                'is_required' => true,
                'min_value' => '1',
            ]);
            ProcurementField::create([
                'category_id' => $category->getKey(),
                'key' => 'departure_batch',
                'label' => 'Batch keberangkatan',
                'field_type' => ProcurementFieldType::Relation,
                'sort_order' => 40,
                'is_required' => true,
                'options' => [$batch->getKey() => $batch->name],
            ]);
        });
    }
}
