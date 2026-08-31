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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseRequestFieldValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_required_typed_and_relation_fields_are_rejected_before_persistence(): void
    {
        $category = ProcurementCategory::factory()->withSupplyFields()->create();
        [$user] = $this->authorizedContext();

        try {
            app(ProcurementRequestDraftSaver::class)->save([
                'category_id' => $category->getKey(),
                'reason' => 'Uniform specification',
                'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
                'fields' => [
                    'size' => 'XXL',
                    'pilgrim_count' => 'many',
                    'departure_batch' => 999999,
                ],
            ], user: $user);
            $this->fail('Invalid dynamic fields were accepted.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertArrayHasKey('fields.size', $errors);
            $this->assertArrayHasKey('fields.color', $errors);
            $this->assertArrayHasKey('fields.pilgrim_count', $errors);
            $this->assertArrayHasKey('fields.departure_batch', $errors);
        }

        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('purchase_request_field_values', 0);
    }

    public function test_review_only_values_cannot_be_written_by_the_requester_and_review_can_edit_them(): void
    {
        $category = ProcurementCategory::factory()->create();
        [$user] = $this->authorizedContext();
        $requesterField = ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'requester_note',
            'label' => 'Catatan pengaju',
            'editable_stage' => ProcurementField::EDITABLE_STAGE_DRAFT,
        ]);
        $reviewField = ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'review_note',
            'label' => 'Catatan review',
            'editable_stage' => ProcurementField::EDITABLE_STAGE_REVIEW,
        ]);
        $saver = app(ProcurementRequestDraftSaver::class);

        $request = $saver->save([
            'category_id' => $category->getKey(),
            'reason' => 'Needs review',
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
            'fields' => [
                'requester_note' => 'Original requester value',
                'review_note' => 'Tampered requester value',
            ],
        ], user: $user);

        $this->assertDatabaseHas('purchase_request_field_values', [
            'purchase_request_id' => $request->getKey(),
            'field_id' => $requesterField->getKey(),
            'field_key' => 'requester_note',
        ]);
        $this->assertDatabaseMissing('purchase_request_field_values', ['field_id' => $reviewField->getKey()]);

        $updated = $saver->save([
            'fields' => [
                'requester_note' => 'Tampered review value',
                'review_note' => 'Procurement decision',
            ],
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
        ], $request->refresh(), $user, ProcurementField::EDITABLE_STAGE_REVIEW);

        $values = $updated->fieldValues()->get()->keyBy('field_key');
        $this->assertSame('Original requester value', $values['requester_note']->value);
        $this->assertSame('Procurement decision', $values['review_note']->value);
    }

    public function test_saved_dynamic_values_retain_definition_key_and_version(): void
    {
        $category = ProcurementCategory::factory()->create();
        [$user] = $this->authorizedContext();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->getKey(),
            'key' => 'color',
            'label' => 'Warna',
            'version' => 3,
            'is_required' => true,
        ]);

        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->getKey(),
            'reason' => 'Color specification',
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
            'fields' => ['color' => 'Blue'],
        ], user: $user);

        $value = $request->fieldValues()->firstOrFail();
        $this->assertSame('color', $value->field_key);
        $this->assertSame(3, $value->field_version);
        $this->assertSame('color', $value->definition_snapshot['key']);
        $this->assertSame($field->version, $value->field_version);
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
