<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DepartureBatch;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\PurchaseRequestNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_requests'));
        $this->assertTrue(Schema::hasTable('purchase_request_items'));

        $this->assertTrue(Schema::hasColumns('purchase_requests', [
            'id', 'pr_number', 'office_id', 'branch_id', 'department_id',
            'cost_center_id', 'departure_batch_id', 'requester_id',
            'title', 'notes', 'required_date', 'status', 'total_amount',
            'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_request_items', [
            'id', 'purchase_request_id', 'procurement_item_id', 'procurement_unit_id',
            'item_name', 'unit_name', 'quantity', 'unit_price', 'line_total',
            'notes', 'sort_order', 'created_at', 'updated_at',
        ]));
    }

    public function test_pr_number_is_assigned_automatically_in_server_sequence(): void
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

        $this->assertMatchesRegularExpression('/^PR-\d{6}-\d{4}$/', $first->pr_number);
        $this->assertMatchesRegularExpression('/^PR-\d{6}-\d{4}$/', $second->pr_number);
        $this->assertNotSame($first->pr_number, $second->pr_number);

        // Same month => sequential suffix.
        $firstMonth = substr($first->pr_number, 3, 6);
        $secondMonth = substr($second->pr_number, 3, 6);
        $this->assertSame($firstMonth, $secondMonth);

        $firstSeq = (int) substr($first->pr_number, -4);
        $secondSeq = (int) substr($second->pr_number, -4);
        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    public function test_number_service_resumes_after_existing_sequence(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        PurchaseRequest::create(['office_id' => $office->id, 'requester_id' => $user->id]);

        $service = app(PurchaseRequestNumberService::class);
        $next = $service->next();

        $this->assertMatchesRegularExpression('/^PR-\d{6}-\d{4}$/', $next);
        $this->assertSame('0002', substr($next, -4));
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

    public function test_creating_header_with_client_total_is_overridden_by_server_calc(): void
    {
        $request = PurchaseRequest::factory()->create(['total_amount' => 999999]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'quantity' => 5,
            'unit_price' => 2000,
        ]);

        $request->recalculateTotal();

        $this->assertSame('10000.00', $request->total_amount);
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
        $batch = DepartureBatch::factory()->create();
        $user = User::factory()->create();

        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'cost_center_id' => $costCenter->id,
            'departure_batch_id' => $batch->id,
            'requester_id' => $user->id,
        ]);

        $this->assertTrue($request->branch->is($branch));
        $this->assertTrue($request->department->is($department));
        $this->assertTrue($request->costCenter->is($costCenter));
        $this->assertTrue($request->departureBatch->is($batch));
        $this->assertTrue($request->requester->is($user));
        $this->assertTrue($request->office->is($office));
    }

    public function test_items_relate_to_master_data_and_header(): void
    {
        $procurementItem = ProcurementItem::factory()->create();
        $unit = ProcurementUnit::factory()->create();
        $request = PurchaseRequest::factory()->create();

        $item = PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'procurement_item_id' => $procurementItem->id,
            'procurement_unit_id' => $unit->id,
        ]);

        // The PurchaseRequest model carries an office global scope; the
        // belongsTo load is also scoped, so read across offices when
        // no authenticated office is present in the test.
        $this->assertTrue($item->purchaseRequest()->acrossOffices()->first()->is($request));
        $this->assertTrue($item->procurementItem->is($procurementItem));
        $this->assertTrue($item->procurementUnit->is($unit));
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
}
