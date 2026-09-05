<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestInfolist;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\PurchaseRequestNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\ProgressStepper\Infolists\Components\ProgressStepper;

class PurchaseRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_requests'));
        $this->assertTrue(Schema::hasTable('purchase_request_items'));
        $this->assertTrue(Schema::hasColumns('purchase_requests', [
            'id', 'pr_number', 'office_id', 'branch_id', 'department_id',
            'cost_center_id', 'umrah_batch_id', 'requester_id',
            'title', 'notes', 'reason', 'required_date', 'priority', 'status', 'total_amount',
            'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_request_items', [
            'id', 'purchase_request_id', 'procurement_item_id', 'procurement_unit_id',
            'item_name', 'description', 'unit_name', 'specifications', 'quantity', 'unit_price', 'line_total',
            'notes', 'sort_order', 'created_at', 'updated_at',
        ]));
    }

    public function test_drafts_use_unique_placeholders_without_allocating_final_numbers(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        $first = PurchaseRequest::create([
            'office_id' => $office->id,
            'requester_id' => $user->id,
            'title' => 'First',
        ]);
        $second = PurchaseRequest::create([
            'office_id' => $office->id,
            'requester_id' => $user->id,
            'title' => 'Second',
        ]);

        $this->assertSame('DRAFT-'.$first->id, $first->pr_number);
        $this->assertSame('DRAFT-'.$second->id, $second->pr_number);
        $this->assertNotSame($first->pr_number, $second->pr_number);
        $this->assertDatabaseMissing('purchase_request_number_sequences', [
            'month' => now()->format('Ym'),
        ]);
    }

    public function test_number_service_starts_after_drafts_without_treating_placeholders_as_numbers(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        PurchaseRequest::create(['office_id' => $office->id, 'requester_id' => $user->id]);

        $next = app(PurchaseRequestNumberService::class)->next();

        $this->assertMatchesRegularExpression('/^PR-\\d{6}-\\d{4}$/', $next);
        $this->assertSame('0001', substr($next, -4));
    }

    public function test_line_total_is_calculated_server_side(): void
    {
        $item = PurchaseRequestItem::factory()->create([
            'quantity' => 3,
            'unit_price' => 12500.50,
        ]);

        $this->assertSame('37501.50', $item->line_total);
    }

    public function test_header_total_is_sum_of_line_totals(): void
    {
        $request = PurchaseRequest::factory()->create();

        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'quantity' => 2,
            'unit_price' => 10000,
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'quantity' => 1,
            'unit_price' => 25000,
        ]);

        $request->recalculateTotal();

        $this->assertSame('45000.00', $request->total_amount);
    }

    public function test_creating_header_always_starts_with_server_total_even_when_client_supplies_one(): void
    {
        $request = PurchaseRequest::factory()->create(['total_amount' => 999999]);

        $this->assertSame('0.00', $request->total_amount);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'total_amount' => 0,
        ]);
    }

    public function test_draft_placeholders_remain_unique_for_many_creates(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        $numbers = collect(range(1, 25))->map(fn (): string => PurchaseRequest::create([
            'office_id' => $office->id,
            'requester_id' => $user->id,
        ])->pr_number);

        $this->assertCount(25, $numbers->unique());
        $this->assertTrue($numbers->every(fn (string $number): bool => str_starts_with($number, 'DRAFT-')));
        $this->assertDatabaseMissing('purchase_request_number_sequences', [
            'month' => now()->format('Ym'),
        ]);
    }

    public function test_sequence_row_is_locked_during_transactional_allocation(): void
    {
        $service = app(PurchaseRequestNumberService::class);

        // Each allocation runs in its own transaction, matching the
        // transaction boundary used by PurchaseRequest::save(). The service
        // locks the shared monthly row before reading and incrementing it;
        // this regression test exercises that path and verifies that each
        // committed allocation advances the sequence without gaps.
        $numbers = collect(range(1, 8))->map(
            fn (): string => DB::transaction(fn (): string => $service->next())
        );

        $this->assertSame(8, $numbers->unique()->count());
        $this->assertSame(
            range(1, 8),
            $numbers->map(fn (string $number): int => (int) substr($number, -4))->all()
        );
        $this->assertDatabaseHas('purchase_request_number_sequences', [
            'month' => now()->format('Ym'),
            'next_sequence' => 9,
        ]);
    }

    public function test_items_cascade_on_purchase_request_delete(): void
    {
        $request = PurchaseRequest::factory()->create();
        $item = PurchaseRequestItem::factory()->create(['purchase_request_id' => $request->id]);

        $request->delete();

        $this->assertDatabaseMissing('purchase_request_items', ['id' => $item->id]);
    }

    public function test_office_delete_is_restricted_by_purchase_requests(): void
    {
        $office = Office::factory()->create();
        PurchaseRequest::factory()->create(['office_id' => $office->id]);

        $this->expectException(QueryException::class);
        $office->delete();
    }

    public function test_requester_delete_is_restricted(): void
    {
        $user = User::factory()->create();
        PurchaseRequest::factory()->create(['requester_id' => $user->id]);

        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_optional_organization_scopes_are_related(): void
    {
        $office = Office::factory()->create();
        $branch = Branch::create(['office_id' => $office->id, 'code' => 'BDG', 'name' => 'Bandung']);
        $department = Department::create(['office_id' => $office->id, 'branch_id' => $branch->id, 'code' => 'OPS', 'name' => 'Operasional']);
        $costCenter = CostCenter::create(['office_id' => $office->id, 'code' => 'CC-01', 'name' => 'Umroh Reguler']);
        $batch = UmrahBatch::factory()->create(['office_id' => $office->id]);
        $user = User::factory()->create();

        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'cost_center_id' => $costCenter->id,
            'umrah_batch_id' => $batch->id,
            'requester_id' => $user->id,
        ]);

        $this->assertTrue($request->branch->is($branch));
        $this->assertTrue($request->department->is($department));
        $this->assertTrue($request->costCenter->is($costCenter));
        $this->assertTrue($request->umrahBatch->is($batch));
    }

    public function test_items_relate_to_master_data_and_header(): void
    {
        $procurementItem = ProcurementItem::factory()->create();
        $request = PurchaseRequest::factory()->create();

        $item = PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'procurement_item_id' => $procurementItem->id,
            'procurement_unit_id' => $procurementItem->unit_id,
        ]);

        // The PurchaseRequest model carries an office global scope; the
        // belongsTo load is also scoped, so read across offices when
        // no authenticated office is present in the test.
        $this->assertTrue($item->purchaseRequest()->acrossOffices()->first()->is($request));
        $this->assertTrue($item->procurementItem->is($procurementItem));
        $this->assertTrue($item->procurementUnit->is($procurementItem->unit));
    }

    public function test_status_defaults_to_draft_and_allows_valid_transitions(): void
    {
        $request = PurchaseRequest::factory()->create();
        $this->assertSame(PurchaseRequest::STATUS_DRAFT, $request->status);

        foreach (PurchaseRequest::STATUSES as $status) {
            $request->update(['status' => $status]);
            $this->assertSame($status, $request->fresh()->status);
        }
    }

    public function test_office_scoping_filters_by_active_office(): void
    {
        $user = User::factory()->create();
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        PurchaseRequest::factory()->create(['office_id' => $officeA->id, 'requester_id' => $user->id]);
        PurchaseRequest::factory()->create(['office_id' => $officeB->id, 'requester_id' => $user->id]);

        // No active office => fail closed, nothing visible.
        $this->assertSame(0, PurchaseRequest::count());

        // Scope to office A.
        $this->actingAs($user);
        $this->assertSame(1, PurchaseRequest::forOffice($officeA->id)->count());
        $this->assertSame(2, PurchaseRequest::acrossOffices()->count());
    }

    public function test_timeline_orders_events_and_uses_distinct_decision_colors(): void
    {
        $createdAt = now()->startOfSecond();
        $request = PurchaseRequest::factory()->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $actor = User::factory()->create();

        PurchaseRequestStatusHistory::factory()->for($request)->create([
            'actor_id' => $actor->getKey(),
            'from_status' => PurchaseRequest::STATUS_DRAFT,
            'to_status' => PurchaseRequest::STATUS_SUBMITTED,
            'event' => 'submitted',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        PurchaseRequestStatusHistory::factory()->for($request)->create([
            'actor_id' => $actor->getKey(),
            'from_status' => PurchaseRequest::STATUS_SUBMITTED,
            'to_status' => PurchaseRequest::STATUS_RETURNED,
            'event' => 'returned',
            'decision' => 'return',
            'note' => 'Please correct the requested quantity.',
            'created_at' => $createdAt->copy()->addSecond(),
            'updated_at' => $createdAt->copy()->addSecond(),
        ]);
        PurchaseRequestStatusHistory::factory()->for($request)->create([
            'actor_id' => $actor->getKey(),
            'from_status' => PurchaseRequest::STATUS_RETURNED,
            'to_status' => PurchaseRequest::STATUS_REJECTED,
            'event' => 'rejected',
            'decision' => 'reject',
            'created_at' => $createdAt->copy()->addSeconds(2),
            'updated_at' => $createdAt->copy()->addSeconds(2),
        ]);

        $entries = PurchaseRequestInfolist::timelineEntries($request);

        $this->assertSame(
            ['PR dibuat', 'Diajukan', 'Perlu perbaikan', 'Ditolak'],
            array_column($entries, 'event'),
        );
        $this->assertSame(
            ['primary', 'primary', 'warning', 'danger'],
            array_column($entries, 'color'),
        );
        $this->assertSame('pengajuan', $entries[2]['stage_key']);
        $this->assertStringContainsString('Catatan perbaikan:', $entries[2]['description']);
    }

    public function test_workflow_stepper_uses_the_outlined_chevron_layout(): void
    {
        $sections = PurchaseRequestInfolist::configure(\Filament\Schemas\Schema::make())->getComponents();
        $workflowSection = collect($sections)->first(
            fn ($section): bool => $section->getHeading() === 'Status & Riwayat — Progres Workflow',
        );
        $stepper = collect($workflowSection->getDefaultChildComponents())->first(
            fn ($component): bool => $component instanceof ProgressStepper,
        );

        $this->assertInstanceOf(ProgressStepper::class, $stepper);
        $this->assertSame('outlined', $stepper->getTheme());
        $this->assertSame('chevron', $stepper->getConnectorShape());
    }

    public function test_completed_workflow_uses_real_stage_names_and_a_clear_terminal_history_entry(): void
    {
        $request = PurchaseRequest::factory()->create([
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);
        PurchaseRequest::query()->withoutGlobalScopes()->whereKey($request->getKey())->update([
            'pr_number' => 'PR-202609-0042',
        ]);
        $request->refresh();
        $actor = User::factory()->create();
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->getKey(),
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $request->requester_id,
            'office_id' => $request->office_id,
            'status' => 'approved',
        ]);
        ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->getKey(),
            'step_order' => 1,
            'step_key' => 'acc_kepala_divisi',
            'label' => 'Acc Kepala Divisi',
            'status' => 'approved',
        ]);
        ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->getKey(),
            'step_order' => 2,
            'step_key' => 'acc_keuangan',
            'label' => 'Acc Keuangan',
            'status' => 'approved',
        ]);
        PurchaseRequestStatusHistory::factory()->for($request)->create([
            'actor_id' => $actor->getKey(),
            'from_status' => 'acc_keuangan',
            'to_status' => PurchaseRequest::STATUS_APPROVED,
            'event' => 'approval_decision',
            'decision' => 'approved',
            'note' => 'Approval workflow completed.',
        ]);

        $this->assertSame([
            'pengajuan' => 'Pengajuan',
            'acc_kepala_divisi' => 'Acc Kepala Divisi',
            'acc_keuangan' => 'Acc Keuangan',
        ], PurchaseRequestInfolist::workflowOptions($request));
        $this->assertTrue(PurchaseRequestInfolist::workflowIsComplete($request));

        $terminalEntry = collect(PurchaseRequestInfolist::timelineEntries($request))->last();
        $this->assertSame('PR selesai', $terminalEntry['event']);
        $this->assertSame('success', $terminalEntry['color']);
        $this->assertTrue($terminalEntry['is_terminal']);
        $this->assertStringContainsString('Disetujui '.$actor->name.' pada tahap Acc Keuangan.', $terminalEntry['description']);
        $this->assertStringContainsString('PR PR-202609-0042 telah disetujui.', $terminalEntry['description']);
        $this->assertSame('acc_keuangan', $terminalEntry['stage_key']);
    }
}
