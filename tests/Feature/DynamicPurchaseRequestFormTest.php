<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPurchaseRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_fields_preserve_labels_defaults_options_and_component_types(): void
    {
        $category = ProcurementCategory::factory()->withSupplyFields()->create();

        $components = PurchaseRequestResource::dynamicFieldComponents($category->getKey());

        $this->assertSame(
            ['fields.size', 'fields.color', 'fields.pilgrim_count', 'fields.departure_batch'],
            collect($components)->map(fn (object $component): string => $component->getName())->all(),
        );
        $this->assertInstanceOf(Select::class, $components[0]);
        $this->assertSame('Ukuran', $components[0]->getLabel());
        $this->assertSame('M', $components[0]->getDefaultState());
        $this->assertSame(['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'], $components[0]->getOptions());
        $this->assertInstanceOf(Select::class, $components[1]);
        $this->assertInstanceOf(TextInput::class, $components[2]);
        $this->assertInstanceOf(Select::class, $components[3]);
        $this->assertSame('Batch keberangkatan', $components[3]->getLabel());
    }

    public function test_dynamic_field_editability_follows_the_current_stage(): void
    {
        $category = ProcurementCategory::factory()->create();
        $requesterField = ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'requester_note',
            'editable_stage' => ProcurementField::EDITABLE_STAGE_DRAFT,
        ]);
        $reviewField = ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'review_note',
            'editable_stage' => ProcurementField::EDITABLE_STAGE_REVIEW,
        ]);

        $components = PurchaseRequestResource::dynamicFieldComponents(
            $category->getKey(),
            ProcurementField::EDITABLE_STAGE_REVIEW,
        );
        $this->assertSame('fields.'.$requesterField->key, $components[0]->getName());
        $this->assertSame('fields.'.$reviewField->key, $components[1]->getName());
    }
}
