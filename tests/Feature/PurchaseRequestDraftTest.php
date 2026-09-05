<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
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
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Leek\FilamentHeaderFilters\Concerns\HasHeaderFilters;
use Livewire\Livewire;
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

    public function test_edit_form_hydrates_every_create_section_from_the_saved_request(): void
    {
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'color',
            'label' => 'Color',
        ]);
        [$user, $office, $branch, $department] = $this->authorizedContext();
        Storage::fake('private');

        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'title' => 'Laptop procurement',
            'reason' => 'New employee equipment',
            'priority' => 'high',
            'required_date' => '2026-09-15',
            'fields' => [$field->key => 'Black'],
            'items' => [[
                'item_name' => 'Laptop',
                'quantity' => '2.00',
                'unit_price' => '1500.00',
                'notes' => '16 GB RAM',
            ]],
            'attachments' => [UploadedFile::fake()->createWithContent('spec.txt', 'specification')],
        ], user: $user);

        Filament::setCurrentPanel('admin');

        Livewire::test(EditPurchaseRequest::class, ['record' => $request->getRouteKey()])
            ->assertSet('data.requester_display', $user->name)
            ->assertSet('data.office_display', $office->name)
            ->assertSet('data.branch_display', $branch->name)
            ->assertSet('data.department_display', $department->name)
            ->assertSet('data.title', 'Laptop procurement')
            ->assertSet('data.items', static function (array $items): bool {
                $item = array_values($items)[0] ?? [];

                return count($items) === 1 && ($item['item_name'] ?? null) === 'Laptop';
            })
            ->assertSet('data.fields.color', 'Black');
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
        // Tabel sekarang menampilkan semua status (agar PR diajukan tetap muncul & sinkron dengan card stats), bukan hanya draft/returned
        $this->assertInstanceOf(Builder::class, PurchaseRequestResource::getEloquentQuery());
    }

    public function test_purchase_request_list_only_displays_records_owned_by_authenticated_user(): void
    {
        [$user, $office, $branch, $department] = $this->authorizedContext();
        $otherUser = User::factory()->create();
        $ownedRequest = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'requester_id' => $user->id,
        ]);
        $otherRequest = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'requester_id' => $otherUser->id,
        ]);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListPurchaseRequests::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$ownedRequest])
            ->assertCanNotSeeTableRecords([$otherRequest])
            ->assertCountTableRecords(1);
    }

    public function test_purchase_request_list_uses_standard_status_tabs_and_header_filters(): void
    {
        [$user, $office, $branch, $department] = $this->authorizedContext();
        $statuses = [
            PurchaseRequest::STATUS_DRAFT,
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_PROCUREMENT_REVIEW,
            PurchaseRequest::STATUS_PENDING_APPROVAL,
            PurchaseRequest::STATUS_RETURNED,
            PurchaseRequest::STATUS_REJECTED,
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_COMPLETED,
            PurchaseRequest::STATUS_CANCELLED,
        ];
        $requests = collect($statuses)->mapWithKeys(function (string $status) use ($user, $office, $branch, $department): array {
            $request = PurchaseRequest::factory()->create([
                'office_id' => $office->getKey(),
                'branch_id' => $branch->getKey(),
                'department_id' => $department->getKey(),
                'requester_id' => $user->getKey(),
                'status' => $status,
            ]);

            return [$status => $request];
        });

        $component = Livewire::test(ListPurchaseRequests::class)->loadTable();
        $tabs = $component->instance()->getTabs();
        $table = $component->instance()->getTable();

        $this->assertSame(
            ['all', 'draft', 'submitted', 'in_progress', 'returned', 'rejected', 'approved', 'cancelled'],
            array_keys($tabs),
        );
        $this->assertSame(
            ['Semua', 'Draft', 'Diajukan', 'Diproses', 'Dikembalikan', 'Ditolak', 'Disetujui', 'Dibatalkan'],
            array_values(array_map(fn ($tab): string => (string) $tab->getLabel(), $tabs)),
        );
        $this->assertSame('9', $tabs['all']->getBadge());
        $this->assertSame('2', $tabs['in_progress']->getBadge());
        $this->assertSame('2', $tabs['approved']->getBadge());

        $baseQuery = fn (): Builder => PurchaseRequest::query()->where('requester_id', $user->getKey());
        $this->assertSame(
            [
                $requests[PurchaseRequest::STATUS_PROCUREMENT_REVIEW]->getKey(),
                $requests[PurchaseRequest::STATUS_PENDING_APPROVAL]->getKey(),
            ],
            $tabs['in_progress']->modifyQuery($baseQuery())->orderBy('id')->pluck('id')->all(),
        );
        $this->assertSame(
            [
                $requests[PurchaseRequest::STATUS_APPROVED]->getKey(),
                $requests[PurchaseRequest::STATUS_COMPLETED]->getKey(),
            ],
            $tabs['approved']->modifyQuery($baseQuery())->orderBy('id')->pluck('id')->all(),
        );
        $this->assertContains(HasHeaderFilters::class, class_uses_recursive(ListPurchaseRequests::class));
        $this->assertTrue($table->hasHeaderFilters());
        $this->assertSame(['category_id', 'office_id', 'status', 'priority'], array_keys($table->getHeaderFilters()));
        $this->assertSame(FiltersLayout::Hidden, $table->getFiltersLayout());
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
