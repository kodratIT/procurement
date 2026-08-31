<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ProcurementRequestDraftSaver;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseRequestDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_saves_complete_draft_in_active_context(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'material',
            'label' => 'Material',
        ]);
        [$user, $office, $branch, $department] = $this->authorizedContext();
        Storage::fake('private');

        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'reason' => 'Office equipment is required',
            'priority' => 'high',
            'required_date' => '2026-09-15',
            'fields' => ['material' => 'Aluminium'],
            'items' => [[
                'item_name' => 'Desk',
                'description' => 'Adjustable desk',
                'specifications' => ['width' => '120cm'],
                'quantity' => '2.00',
                'unit_price' => '1250.50',
            ]],
            'attachments' => [UploadedFile::fake()->createWithContent('quote.txt', 'supplier quote')],
        ], user: $user);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'requester_id' => $user->id,
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'status' => PurchaseRequest::STATUS_DRAFT,
            'priority' => 'high',
            'total_amount' => '2501.00',
        ]);
        $this->assertSame('DRAFT-'.$request->id, $request->pr_number);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $request->id,
            'quantity' => '2.00',
            'unit_price' => '1250.50',
            'line_total' => '2501.00',
        ]);
        $this->assertDatabaseHas('purchase_request_field_values', [
            'purchase_request_id' => $request->id,
            'field_id' => $field->id,
            'field_key' => 'material',
            'value' => json_encode('Aluminium'),
        ]);
        $this->assertCount(1, $request->attachments);
        $this->assertSame('private', $request->attachments->first()->disk);
    }

    public function test_authorized_user_can_reopen_and_edit_structured_and_dynamic_draft_values(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'color',
            'label' => 'Color',
        ]);
        [$user, $office, $branch, $department] = $this->authorizedContext();
        $saver = app(ProcurementRequestDraftSaver::class);
        $request = $saver->save([
            'category_id' => $category->id,
            'reason' => 'Initial reason',
            'items' => [['item_name' => 'Chair', 'quantity' => 1, 'unit_price' => '100.00']],
            'fields' => ['color' => 'Blue'],
        ], user: $user);

        $updated = $saver->save([
            'reason' => 'Updated reason',
            'priority' => 'urgent',
            'items' => [['item_name' => 'Chair', 'quantity' => '3.00', 'unit_price' => '110.00']],
            'fields' => ['color' => 'Green'],
        ], $request->refresh(), $user);

        $this->assertSame($request->id, $updated->id);
        $this->assertSame($user->id, $updated->requester_id);
        $this->assertSame($office->id, $updated->office_id);
        $this->assertSame($branch->id, $updated->branch_id);
        $this->assertSame($department->id, $updated->department_id);
        $this->assertSame(PurchaseRequest::STATUS_DRAFT, $updated->status);
        $this->assertSame('urgent', $updated->priority);
        $this->assertSame('330.00', $updated->total_amount);
        $this->assertSame('Green', $updated->fieldValues()->first()->value);
        $this->assertSame($field->id, $updated->fieldValues()->first()->field_id);
    }

    public function test_draft_save_requires_authorization_and_active_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        app(ProcurementRequestDraftSaver::class)->save(['items' => []], user: $user);
    }

    public function test_resource_is_registered_and_only_exposes_draft_records(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $panel = Filament::getPanel('admin');

        $this->assertContains(PurchaseRequestResource::class, $panel->getResources());
        $this->assertSame(PurchaseRequest::class, PurchaseRequestResource::getModel());
        $this->assertStringContainsString('status', PurchaseRequestResource::getEloquentQuery()->toRawSql());
    }

    /** @return array{User, Office, Branch, Department} */
    private function authorizedContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $branch = Branch::factory()->create(['office_id' => $office->id]);
        $department = Department::factory()->create(['office_id' => $office->id, 'branch_id' => $branch->id]);
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'role_id' => $role->id,
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        app(AccessContextService::class)->setContext($assignment);

        return [$user, $office, $branch, $department];
    }
}
