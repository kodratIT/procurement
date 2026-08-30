<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProcurementFieldType;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Services\DynamicFieldValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicFieldValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_supported_types_accept_valid_values(): void
    {
        $category = ProcurementCategory::factory()->create();
        $fields = collect([
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'name', 'field_type' => ProcurementFieldType::Text, 'is_required' => true, 'min_value' => '2', 'max_value' => '20']),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'details', 'field_type' => ProcurementFieldType::Textarea]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'quantity', 'field_type' => ProcurementFieldType::Number, 'min_value' => '1', 'max_value' => '10']),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'budget', 'field_type' => ProcurementFieldType::Currency, 'min_value' => '0', 'max_value' => '1000000']),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'needed_on', 'field_type' => ProcurementFieldType::Date]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'travel_dates', 'field_type' => ProcurementFieldType::DateRange]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'room', 'field_type' => ProcurementFieldType::Dropdown, 'options' => ['single' => 'Single', 'double' => 'Double']]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'vehicle', 'field_type' => ProcurementFieldType::Radio, 'options' => ['bus', 'van']]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'insured', 'field_type' => ProcurementFieldType::Checkbox]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'document', 'field_type' => ProcurementFieldType::Upload]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'vendor', 'field_type' => ProcurementFieldType::Relation, 'options' => [1 => 'Vendor A']]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'variant', 'field_type' => ProcurementFieldType::Variant, 'options' => [2 => 'Large']]),
        ]);

        $values = [
            'name' => 'Koper',
            'details' => 'Koper kabin',
            'quantity' => 4,
            'budget' => '125000.00',
            'needed_on' => '2026-09-15',
            'travel_dates' => ['2026-09-15', '2026-09-20'],
            'room' => 'double',
            'vehicle' => 'bus',
            'insured' => true,
            'document' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
            'vendor' => 1,
            'variant' => 2,
        ];

        $validated = app(DynamicFieldValidator::class)->validate($fields, $values);

        $this->assertSame($values, $validated);
    }

    public function test_requiredness_type_bounds_and_options_are_enforced(): void
    {
        $category = ProcurementCategory::factory()->create();
        $fields = collect([
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'title', 'field_type' => ProcurementFieldType::Text, 'is_required' => true]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'quantity', 'field_type' => ProcurementFieldType::Number, 'min_value' => '1', 'max_value' => '10']),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'room', 'field_type' => ProcurementFieldType::Dropdown, 'options' => ['single' => 'Single']]),
            ProcurementField::factory()->create(['category_id' => $category->id, 'key' => 'date', 'field_type' => ProcurementFieldType::Date, 'min_value' => '2026-09-01', 'max_value' => '2026-09-30']),
        ]);

        $validator = app(DynamicFieldValidator::class)->validator($fields, [
            'quantity' => 'not-a-number',
            'room' => 'triple',
            'date' => '2026-08-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
        $this->assertArrayHasKey('room', $validator->errors()->toArray());
        $this->assertArrayHasKey('date', $validator->errors()->toArray());
    }

    public function test_conditional_fields_are_validated_only_when_visible(): void
    {
        $category = ProcurementCategory::factory()->create();
        $fields = collect([
            ProcurementField::factory()->create([
                'category_id' => $category->id,
                'key' => 'needs_hotel',
                'field_type' => ProcurementFieldType::Checkbox,
                'is_required' => true,
            ]),
            ProcurementField::factory()->create([
                'category_id' => $category->id,
                'key' => 'hotel_city',
                'field_type' => ProcurementFieldType::Text,
                'is_required' => true,
                'visibility_conditions' => [
                    ['field' => 'needs_hotel', 'operator' => 'equals', 'value' => true],
                ],
            ]),
        ]);
        $service = app(DynamicFieldValidator::class);

        $this->assertArrayNotHasKey('hotel_city', $service->rules($fields, ['needs_hotel' => false]));
        $this->assertArrayHasKey('hotel_city', $service->rules($fields, ['needs_hotel' => true]));
        $this->assertSame(['needs_hotel' => false], $service->validate($fields, ['needs_hotel' => false]));

        $this->expectException(ValidationException::class);

        $service->validate($fields, ['needs_hotel' => true]);
    }

    public function test_nested_fields_input_and_any_condition_logic_are_supported(): void
    {
        $category = ProcurementCategory::factory()->create();
        $fields = collect([
            ProcurementField::factory()->create([
                'category_id' => $category->id,
                'key' => 'hotel_city',
                'field_type' => ProcurementFieldType::Text,
                'is_required' => true,
                'visibility_conditions' => [
                    'logic' => 'any',
                    'conditions' => [
                        ['field' => 'hotel', 'operator' => 'equals', 'value' => true],
                        ['field' => 'transport', 'operator' => 'equals', 'value' => true],
                    ],
                ],
            ]),
        ]);

        $rules = app(DynamicFieldValidator::class)->rules($fields, ['hotel' => false, 'transport' => true]);

        $this->assertArrayHasKey('hotel_city', $rules);
        $this->assertSame(['fields' => ['hotel_city' => 'Jakarta']], app(DynamicFieldValidator::class)->validate($fields, [
            'fields' => ['hotel_city' => 'Jakarta'],
            'hotel' => false,
            'transport' => true,
        ]));
    }
}
