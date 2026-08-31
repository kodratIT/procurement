<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetReservation;
use App\Models\CostCenter;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Services\BudgetReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class BudgetReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sufficient_budget_creates_atomic_reservation_and_recalculates_available_amount(): void
    {
        [$budget, $request] = $this->budgetAndRequest('300.00', '1000.00');
        $service = app(BudgetReservationService::class);

        $reservation = $service->reserve($request);

        $this->assertSame(BudgetReservation::STATUS_RESERVED, $reservation->status);
        $this->assertSame('700.00', $service->availableAmount($budget));
        $this->assertDatabaseHas('budget_reservations', [
            'budget_id' => $budget->id,
            'purchase_request_id' => $request->id,
            'amount' => '300.00',
            'status' => BudgetReservation::STATUS_RESERVED,
        ]);
    }

    public function test_insufficient_budget_reports_shortfall_without_creating_reservation(): void
    {
        [$budget, $firstRequest] = $this->budgetAndRequest('800.00', '1000.00');
        $service = app(BudgetReservationService::class);
        $service->reserve($firstRequest);
        [, $secondRequest] = $this->budgetAndRequest('300.00', '1000.00', $budget);

        try {
            $service->reserve($secondRequest);
            $this->fail('An insufficient budget reservation must fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('shortfall 100.00', (string) $exception->errors()['budget'][0]);
        }

        $this->assertSame('200.00', $service->availableAmount($budget));
        $this->assertDatabaseMissing('budget_reservations', ['purchase_request_id' => $secondRequest->id]);
    }

    public function test_sequential_concurrent_reservation_attempts_cannot_overallocate_budget(): void
    {
        [$budget, $firstRequest] = $this->budgetAndRequest('600.00', '1000.00');
        [, $secondRequest] = $this->budgetAndRequest('600.00', '1000.00', $budget);
        $service = app(BudgetReservationService::class);

        $service->reserve($firstRequest);
        try {
            $service->reserve($secondRequest);
            $this->fail('A concurrent over-allocation attempt must fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame('400.00', $service->availableAmount($budget));
        $this->assertSame(1, $budget->reservations()->available()->count());
    }

    public function test_release_and_revision_audit_before_after_amount_and_reason(): void
    {
        [$budget, $request] = $this->budgetAndRequest('300.00', '1000.00');
        $service = app(BudgetReservationService::class);
        $reservation = $service->reserve($request);

        $service->revise($reservation, '450.00', 'Approved amount changed.');
        $service->release($reservation, 'Purchase request cancelled.');

        $this->assertSame('1000.00', $service->availableAmount($budget));
        $activity = Activity::query()
            ->where('subject_type', $reservation::class)
            ->where('event', 'revised')
            ->latest('id')
            ->firstOrFail();
        $properties = $activity->properties->toArray();
        $this->assertSame('300.00', $properties['before']['amount']);
        $this->assertSame('450.00', $properties['after']['amount']);
        $this->assertSame('Approved amount changed.', $properties['reason']);
    }

    public function test_budget_check_uses_cost_center_owner_scope_and_year(): void
    {
        [$budget, $request] = $this->budgetAndRequest('900.00', '1000.00');
        $service = app(BudgetReservationService::class);

        $this->assertSame($budget->office_id, $service->resolveBudgetOwnerOfficeId($request));
        $this->assertTrue($service->check($request));
        $service->reserve($request);
        $this->assertFalse($service->check($request->fresh()));
    }

    /** @return array{Budget, PurchaseRequest} */
    private function budgetAndRequest(
        string $requestAmount,
        string $budgetAmount,
        ?Budget $budget = null,
    ): array {
        if (! $budget instanceof Budget) {
            $office = Office::factory()->create();
            $costCenter = CostCenter::factory()->create(['office_id' => $office->id]);
            $budget = Budget::factory()->create([
                'office_id' => $office->id,
                'cost_center_id' => $costCenter->id,
                'year' => (int) date('Y'),
                'amount' => $budgetAmount,
            ]);
        } else {
            $office = $budget->office;
            $costCenter = $budget->costCenter;
        }

        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'cost_center_id' => $costCenter->id,
            'required_date' => date('Y-m-d'),
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);
        $request->forceFill(['total_amount' => $requestAmount])->saveQuietly();

        return [$budget, $request];
    }
}
