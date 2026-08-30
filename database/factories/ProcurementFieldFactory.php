<?php

namespace Database\Factories;

use App\Enums\ProcurementFieldType;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementField>
 */
class ProcurementFieldFactory extends Factory
{
    protected $model = ProcurementField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ProcurementCategory::factory(),
            'key' => fake()->unique()->lexify('field_????'),
            'label' => fake()->words(2, true),
            'field_type' => ProcurementFieldType::Text,
            'sort_order' => 0,
            'is_required' => false,
            'options' => null,
            'default_value' => null,
            'min_value' => null,
            'max_value' => null,
            'visibility_conditions' => null,
            'editable_stage' => ProcurementField::EDITABLE_STAGE_DRAFT,
            'version' => 1,
            'is_active' => true,
            'activated_at' => null,
            'deactivated_at' => null,
        ];
    }
}
