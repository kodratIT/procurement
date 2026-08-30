<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\ProcurementCategoryField;
use App\Models\PurchaseRequest;
use App\Services\DynamicFieldValueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicFieldValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_values_are_validated_and_stored_separately_from_financial_data(): void
    {
        $category = ProcurementCategory::create(['code' => 'OFFICE', 'name' => 'Office']);
        $field = ProcurementCategoryField::create([
            'category_id' => $category->id, 'key' => 'color', 'label' => 'Color',
            'type' => 'select', 'is_required' => true, 'options' => ['red', 'blue'],
        ]);
        $request = PurchaseRequest::create([
            'office_id' => Office::factory()->create()->id,
            'requester_id' => \App\Models\User::factory()->create()->id,
        ]);

        $value = app(DynamicFieldValueService::class)->save($request, $field, 'blue');

        $this->assertSame('blue', $value->decodedValue());
        $this->assertSame('0.00', (string) $request->fresh()->total_amount);
        $this->assertSame($field->id, $request->fieldValues()->first()->field_id);
        $this->expectException(ValidationException::class);
        app(DynamicFieldValueService::class)->validate($field, 'green');
    }

    public function test_uploaded_files_use_private_storage_and_path_values_are_rejected_when_unsafe(): void
    {
        Storage::fake('local');
        $field = new ProcurementCategoryField(['key' => 'attachment', 'label' => 'Attachment', 'type' => 'file']);
        $service = app(DynamicFieldValueService::class);

        $this->assertInstanceOf(UploadedFile::class, $service->validate($field, UploadedFile::fake()->create('quote.pdf')));
        $this->expectException(ValidationException::class);
        $service->validate($field, '../public/quote.pdf');
    }
}
