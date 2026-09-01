<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\BudgetReservationService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class BudgetResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_view_scoped_budget_totals(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $finance = User::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $office = Office::factory()->create();
        $costCenter = CostCenter::factory()->create(['office_id' => $office->id]);
        UserAssignment::factory()->create([
            'user_id' => $finance->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'is_primary' => true,
        ]);
        $budget = Budget::factory()->create([
            'office_id' => $office->id,
            'cost_center_id' => $costCenter->id,
            'year' => (int) date('Y'),
            'amount' => '1000.00',
        ]);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'cost_center_id' => $costCenter->id,
            'required_date' => date('Y-m-d'),
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);
        $request->forceFill(['total_amount' => '250.00'])->saveQuietly();

        $this->actingAs($finance);
        $this->assertTrue(Gate::forUser($finance)->allows('view', $budget));
        app(BudgetReservationService::class)->reserve($request);

        $totals = app(BudgetReservationService::class)->totals($budget);

        $this->assertTrue(Gate::forUser($finance)->allows('view', $budget));
        $this->assertSame(['allocation' => '1000.00', 'reserved' => '250.00', 'committed' => '0.00', 'available' => '750.00'], $totals);
    }

    public function test_finance_resource_excludes_budget_from_another_office(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $finance = User::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        UserAssignment::factory()->create([
            'user_id' => $finance->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'is_primary' => true,
        ]);
        $budget = Budget::factory()->create([
            'office_id' => $otherOffice->id,
            'cost_center_id' => CostCenter::factory()->create(['office_id' => $otherOffice->id])->id,
            'year' => (int) date('Y'),
        ]);

        $this->actingAs($finance);

        $this->assertFalse(Gate::forUser($finance)->allows('view', $budget));
        $this->assertSame([], BudgetResource::getEloquentQuery()->pluck('id')->all());
    }
}
