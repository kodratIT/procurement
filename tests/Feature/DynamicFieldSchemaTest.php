<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProcurementFieldType;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFieldValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicFieldSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_field_tables_have_definition_and_historical_value_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('procurement_fields', [
            'id', 'category_id', 'key', 'label', 'field_type', 'sort_order',
            'is_required', 'options', 'default_value', 'min_value', 'max_value',
            'visibility_conditions', 'editable_stage', 'version', 'is_active',
            'activated_at', 'deactivated_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('purchase_request_field_values', [
            'id', 'purchase_request_id', 'field_id', 'field_key', 'field_label',
            'field_type', 'field_version', 'definition_snapshot', 'value',
            'created_at', 'updated_at',
        ]));

        $fieldIndexes = collect(Schema::getIndexes('procurement_fields'))->pluck('columns')->all();
        $valueIndexes = collect(Schema::getIndexes('purchase_request_field_values'))->pluck('columns')->all();

        $this->assertContains(['category_id', 'key'], $fieldIndexes);
        $this->assertContains(['category_id', 'is_active', 'sort_order'], $fieldIndexes);
        $this->assertContains(['purchase_request_id', 'field_id'], $valueIndexes);
        $this->assertContains(['field_id', 'field_version'], $valueIndexes);
    }

    public function test_field_definition_is_typed_ordered_and_related_to_a_category(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'room_type',
            'label' => 'Tipe kamar',
            'field_type' => ProcurementFieldType::Dropdown,
            'sort_order' => 3,
            'is_required' => true,
            'options' => ['single' => 'Single', 'double' => 'Double'],
            'default_value' => 'double',
            'min_value' => null,
            'max_value' => null,
            'visibility_conditions' => [
                ['field' => 'needs_hotel', 'operator' => 'equals', 'value' => true],
            ],
            'editable_stage' => ProcurementField::EDITABLE_STAGE_REVIEW,
        ]);

        $this->assertTrue($field->category->is($category));
        $this->assertSame(ProcurementFieldType::Dropdown, $field->field_type);
        $this->assertSame(['single' => 'Single', 'double' => 'Double'], $field->options);
        $this->assertSame('double', $field->default_value);
        $this->assertSame(3, $field->sort_order);
        $this->assertTrue($field->is_required);
        $this->assertSame(1, $field->version);
        $this->assertTrue($field->is_active);
    }

    public function test_field_value_captures_definition_metadata_and_keeps_it_after_definition_changes(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'capacity',
            'label' => 'Kapasitas',
            'field_type' => ProcurementFieldType::Number,
            'version' => 1,
        ]);
        $request = PurchaseRequest::factory()->create(['category_id' => $category->id]);

        $value = PurchaseRequestFieldValue::fromField($request, $field, 40);
        $value->save();

        $field->update([
            'label' => 'Kapasitas kendaraan',
            'min_value' => '1',
        ]);

        $this->assertSame(2, $field->fresh()->version);
        $value = $value->fresh();
        $this->assertSame(1, $value->field_version);
        $this->assertSame('Kapasitas', $value->field_label);
        $this->assertSame('capacity', $value->field_key);
        $this->assertSame(ProcurementFieldType::Number->value, $value->field_type);
        $this->assertSame('Kapasitas', $value->definition_snapshot['label']);
        $this->assertSame(40, $value->value);
        $this->assertTrue($request->fieldValues()->first()->is($value));
    }

    public function test_field_values_are_unique_per_purchase_request_and_definition(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create(['category_id' => $category->id]);
        $request = PurchaseRequest::factory()->create(['category_id' => $category->id]);
        PurchaseRequestFieldValue::fromField($request, $field, 'first')->save();

        $this->expectException(QueryException::class);

        PurchaseRequestFieldValue::fromField($request, $field, 'second')->save();
    }
}
