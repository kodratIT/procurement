<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ProcurementRequestDraftSaver;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseRequestDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_category_fields_save_ordered_values_and_batch_relation(): void
    {
        $category = ProcurementCategory::factory()->withSupplyFields()->create();
        [$user] = $this->authorizedContext();
        $batchId = ProcurementField::query()
            ->where('category_id', $category->getKey())
            ->where('key', 'departure_batch')
            ->value('options');
        $batchId = (int) array_key_first($batchId);

        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->getKey(),
            'reason' => 'Supply specifications',
            'items' => [['item_name' => 'Uniform', 'quantity' => 45, 'unit_price' => 100]],
            'fields' => [
                'size' => 'L',
                'color' => 'white',
                'pilgrim_count' => 45,
                'departure_batch' => $batchId,
            ],
        ], user: $user);

        $values = $request->fieldValues()->get()->sortBy(
            fn (object $value): int => $value->definition_snapshot['sort_order'],
        )->values();
        $this->assertSame('L', $values->firstWhere('field_key', 'size')->value);
        $this->assertSame(45, $values->firstWhere('field_key', 'pilgrim_count')->value);
        $this->assertSame($batchId, $values->firstWhere('field_key', 'departure_batch')->value);
        $this->assertNotNull($values->firstWhere('field_key', 'departure_batch')->definition_snapshot['version']);
    }

    public function test_conditional_required_field_is_skipped_when_hidden_and_required_when_visible(): void
    {
        $category = ProcurementCategory::factory()->create();
        [$user] = $this->authorizedContext();
        ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'show_color',
            'field_type' => 'checkbox',
        ]);
        ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'color',
            'field_type' => 'text',
            'is_required' => true,
            'visibility_conditions' => [
                ['field' => 'show_color', 'operator' => 'equals', 'value' => true],
            ],
        ]);
        $saver = app(ProcurementRequestDraftSaver::class);

        $request = $saver->save([
            'category_id' => $category->getKey(),
            'reason' => 'Conditional color',
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
            'fields' => ['show_color' => false],
        ], user: $user);

        $this->assertDatabaseMissing('purchase_request_field_values', [
            'purchase_request_id' => $request->getKey(),
            'field_key' => 'color',
        ]);

        try {
            $saver->save([
                'fields' => ['show_color' => true],
                'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
            ], $request->refresh(), $user);
            $this->fail('A visible required field was not validated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fields.color', $exception->errors());
        }

        $this->assertSame(false, $request->refresh()->fieldValues()->firstWhere('field_key', 'show_color')->value);
        $this->assertDatabaseMissing('purchase_request_field_values', ['field_key' => 'color']);
    }

    public function test_valid_upload_field_is_validated_stored_and_linked_to_the_request(): void
    {
        $category = ProcurementCategory::factory()->create();
        [$user] = $this->authorizedContext();
        ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'specification_file',
            'label' => 'Berkas spesifikasi',
            'field_type' => 'upload',
            'is_required' => true,
        ]);
        Storage::fake('private');

        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->getKey(),
            'reason' => 'File specification',
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
            'fields' => [
                'specification_file' => UploadedFile::fake()->createWithContent('specification.txt', 'size: L'),
            ],
        ], user: $user);

        $value = $request->fieldValues()->firstOrFail();
        $this->assertIsString($value->value);
        $this->assertStringEndsWith('.txt', $value->value);
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => $request->getMorphClass(),
            'attachable_id' => $request->getKey(),
            'collection' => 'purchase-request-field-specification_file',
            'original_name' => 'specification.txt',
        ]);
        Storage::disk('private')->assertExists($value->value);

        $updated = app(ProcurementRequestDraftSaver::class)->save([
            'fields' => ['specification_file' => $value->value],
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
        ], $request->refresh(), $user);

        $this->assertSame($value->value, $updated->fieldValues()->firstOrFail()->value);
    }

    /** @return array{User, Office, Branch, Department} */
    private function authorizedContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $branch = Branch::factory()->create(['office_id' => $office->getKey()]);
        $department = Department::factory()->create([
            'office_id' => $office->getKey(),
            'branch_id' => $branch->getKey(),
        ]);
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->getKey(),
            'office_id' => $office->getKey(),
            'branch_id' => $branch->getKey(),
            'department_id' => $department->getKey(),
            'role_id' => $role->getKey(),
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        app(AccessContextService::class)->setContext($assignment);

        return [$user, $office, $branch, $department];
    }
}
