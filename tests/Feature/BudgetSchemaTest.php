<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetReservation;
use App\Models\CostCenter;
use App\Models\Office;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BudgetSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_and_reservation_schema_matches_financial_scope(): void
    {
        $this->assertTrue(Schema::hasColumns('budgets', ['office_id', 'cost_center_id', 'year', 'amount', 'status']));
        $this->assertTrue(Schema::hasColumns('budget_reservations', ['budget_id', 'purchase_request_id', 'amount', 'status']));

        $office = Office::factory()->create();
        $costCenter = CostCenter::factory()->create(['office_id' => $office->id]);
        $budget = Budget::factory()->create([
            'office_id' => $office->id,
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount' => '10000.00',
        ]);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'cost_center_id' => $costCenter->id,
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);
        $reservation = BudgetReservation::factory()->create([
            'budget_id' => $budget->id,
            'purchase_request_id' => $request->id,
            'amount' => '250.00',
        ]);

        $this->assertSame($office->id, $budget->office->id);
        $this->assertSame($costCenter->id, $budget->costCenter->id);
        $this->assertSame($request->id, $reservation->purchase_request_id);
    }
}
