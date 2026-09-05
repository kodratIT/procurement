<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BudgetCheck;
use App\Models\Budget;
use App\Models\BudgetReservation;
use App\Models\CostCenter;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\DomainTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BudgetReservationService implements BudgetCheck
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly FeatureModuleService $featureModules,
    ) {}

    public function check(PurchaseRequest $purchaseRequest): bool
    {
        $this->assertFeature();
        $budget = $this->resolveBudget($purchaseRequest);

        if (! $budget instanceof Budget || $this->moneyCompare((string) $purchaseRequest->total_amount, '0.00') < 0) {
            return false;
        }

        return $this->moneyCompare(
            $this->availableAmount($budget),
            $this->money((string) $purchaseRequest->total_amount),
        ) >= 0;
    }

    public function reserve(
        PurchaseRequest $purchaseRequest,
        ?int $year = null,
        ?User $actor = null,
    ): BudgetReservation {
        $this->assertFeature($actor);
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'purchase_request' => 'Only an approved purchase request can be reserved.',
            ]);
        }

        $amount = $this->money((string) $purchaseRequest->total_amount);
        if ($this->moneyCompare($amount, '0.00') <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'A budget reservation amount must be greater than zero.',
            ]);
        }

        $year ??= $this->requestYear($purchaseRequest);
        $budget = $this->resolveBudget($purchaseRequest, $year);
        if (! $budget instanceof Budget) {
            throw ValidationException::withMessages([
                'budget' => 'No active budget exists for the purchase request scope and year.',
            ]);
        }

        $this->authorizeBudget($budget, $actor);

        return $this->transaction->run(
            'reserve purchase request budget',
            function () use ($budget, $purchaseRequest, $amount, $actor): BudgetReservation {
                $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($budget->getKey());
                $reservation = BudgetReservation::query()
                    ->where('budget_id', $lockedBudget->getKey())
                    ->where('purchase_request_id', $purchaseRequest->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($reservation instanceof BudgetReservation && $reservation->isAvailable()) {
                    if ($this->moneyCompare((string) $reservation->amount, $amount) === 0) {
                        return $reservation;
                    }

                    if ($reservation->status === BudgetReservation::STATUS_COMMITTED) {
                        throw ValidationException::withMessages([
                            'reservation' => 'A committed budget reservation cannot be revised by re-reservation.',
                        ]);
                    }

                    return $this->reviseLocked($lockedBudget, $reservation, $amount, 'Approved amount synchronized.', $actor);
                }

                $available = $this->availableAmount($lockedBudget);
                if ($this->moneyCompare($available, $amount) < 0) {
                    $this->throwInsufficient($amount, $available);
                }

                if ($reservation instanceof BudgetReservation) {
                    $before = $this->snapshot($reservation);
                    $reservation->forceFill([
                        'amount' => $amount,
                        'status' => BudgetReservation::STATUS_RESERVED,
                    ])->save();
                    $this->audit($reservation, $actor, 'reserved', $before, $this->snapshot($reservation), 'Approved amount reserved.');

                    return $reservation->fresh(['budget', 'purchaseRequest']);
                }

                $reservation = $lockedBudget->reservations()->create([
                    'purchase_request_id' => $purchaseRequest->getKey(),
                    'amount' => $amount,
                    'status' => BudgetReservation::STATUS_RESERVED,
                ]);
                $this->audit($reservation, $actor, 'reserved', null, $this->snapshot($reservation), 'Approved amount reserved.');

                return $reservation->fresh(['budget', 'purchaseRequest']);
            },
            [
                'budget_id' => $budget->getKey(),
                'purchase_request_id' => $purchaseRequest->getKey(),
                'amount' => $amount,
            ],
        );
    }

    public function release(
        BudgetReservation|PurchaseRequest|int $reservation,
        string $reason,
        ?User $actor = null,
    ): BudgetReservation {
        $this->assertFeature($actor);
        $this->assertReason($reason);
        $reservationId = $this->reservationId($reservation);

        return $this->transaction->run(
            'release budget reservation',
            function () use ($reservationId, $reason, $actor): BudgetReservation {
                $lockedReservation = BudgetReservation::query()->lockForUpdate()->findOrFail($reservationId);
                $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($lockedReservation->budget_id);
                $this->authorizeBudget($lockedBudget, $actor);

                if ($lockedReservation->status === BudgetReservation::STATUS_RELEASED) {
                    return $lockedReservation->fresh(['budget', 'purchaseRequest']);
                }

                if ($lockedReservation->status === BudgetReservation::STATUS_CANCELLED) {
                    throw ValidationException::withMessages([
                        'reservation' => 'A cancelled budget reservation cannot be released.',
                    ]);
                }

                if ($lockedReservation->status !== BudgetReservation::STATUS_RESERVED
                    && $lockedReservation->status !== BudgetReservation::STATUS_COMMITTED) {
                    throw ValidationException::withMessages([
                        'reservation' => 'Only an active budget reservation can be released.',
                    ]);
                }

                $before = $this->snapshot($lockedReservation);
                $lockedReservation->forceFill(['status' => BudgetReservation::STATUS_RELEASED])->save();
                $this->audit($lockedReservation, $actor, 'released', $before, $this->snapshot($lockedReservation), $reason);

                return $lockedReservation->fresh(['budget', 'purchaseRequest']);
            },
            ['budget_reservation_id' => $reservationId],
        );
    }

    public function cancel(
        BudgetReservation|PurchaseRequest|int $reservation,
        string $reason,
        ?User $actor = null,
    ): BudgetReservation {
        $this->assertFeature($actor);
        $this->assertReason($reason);
        $reservationId = $this->reservationId($reservation);

        return $this->transaction->run(
            'cancel budget reservation',
            function () use ($reservationId, $reason, $actor): BudgetReservation {
                $lockedReservation = BudgetReservation::query()->lockForUpdate()->findOrFail($reservationId);
                $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($lockedReservation->budget_id);
                $this->authorizeBudget($lockedBudget, $actor);

                if ($lockedReservation->status === BudgetReservation::STATUS_CANCELLED) {
                    return $lockedReservation->fresh(['budget', 'purchaseRequest']);
                }

                if (! $lockedReservation->isAvailable()) {
                    throw ValidationException::withMessages([
                        'reservation' => 'Only an active budget reservation can be cancelled.',
                    ]);
                }

                $before = $this->snapshot($lockedReservation);
                $lockedReservation->forceFill(['status' => BudgetReservation::STATUS_CANCELLED])->save();
                $this->audit($lockedReservation, $actor, 'cancelled', $before, $this->snapshot($lockedReservation), $reason);

                return $lockedReservation->fresh(['budget', 'purchaseRequest']);
            },
            ['budget_reservation_id' => $reservationId],
        );
    }

    public function revise(
        BudgetReservation $reservation,
        string|int|float $amount,
        string $reason,
        ?User $actor = null,
    ): BudgetReservation {
        $this->assertFeature($actor);
        $this->assertReason($reason);
        $amount = $this->money($amount);
        if ($this->moneyCompare($amount, '0.00') <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'A budget reservation amount must be greater than zero.',
            ]);
        }

        return $this->transaction->run(
            'revise budget reservation',
            function () use ($reservation, $amount, $reason, $actor): BudgetReservation {
                $lockedReservation = BudgetReservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
                $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($lockedReservation->budget_id);
                $this->authorizeBudget($lockedBudget, $actor);

                if ($lockedReservation->status !== BudgetReservation::STATUS_RESERVED) {
                    throw ValidationException::withMessages([
                        'reservation' => 'Only a reserved amount can be revised.',
                    ]);
                }

                return $this->reviseLocked($lockedBudget, $lockedReservation, $amount, $reason, $actor);
            },
            ['budget_reservation_id' => $reservation->getKey(), 'amount' => $amount],
        );
    }

    public function commit(
        BudgetReservation $reservation,
        ?User $actor = null,
    ): BudgetReservation {
        $this->assertFeature($actor);

        return $this->transaction->run(
            'commit budget reservation',
            function () use ($reservation, $actor): BudgetReservation {
                $lockedReservation = BudgetReservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
                $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($lockedReservation->budget_id);
                $this->authorizeBudget($lockedBudget, $actor);

                if ($lockedReservation->status !== BudgetReservation::STATUS_RESERVED) {
                    throw ValidationException::withMessages([
                        'reservation' => 'Only a reserved amount can be committed.',
                    ]);
                }

                $before = $this->snapshot($lockedReservation);
                $lockedReservation->forceFill(['status' => BudgetReservation::STATUS_COMMITTED])->save();
                $this->audit($lockedReservation, $actor, 'committed', $before, $this->snapshot($lockedReservation), 'Reservation committed.');

                return $lockedReservation->fresh(['budget', 'purchaseRequest']);
            },
            ['budget_reservation_id' => $reservation->getKey()],
        );
    }

    public function availableAmount(Budget|int $budget): string
    {
        $this->assertFeature();
        $budget = $budget instanceof Budget
            ? $budget
            : Budget::query()->findOrFail($budget);

        if ($budget->status !== Budget::STATUS_ACTIVE) {
            return '0.00';
        }

        $reserved = $this->sumReservations($budget, [BudgetReservation::STATUS_RESERVED]);
        $committed = $this->sumReservations($budget, [BudgetReservation::STATUS_COMMITTED]);

        return $this->subtract($this->money($budget->amount), $this->add($reserved, $committed));
    }

    public function available(Budget|int $budget): string
    {
        return $this->availableAmount($budget);
    }

    /** @return array{allocation: string, reserved: string, committed: string, available: string} */
    public function totals(Budget|int $budget): array
    {
        $this->assertFeature();
        $budget = $budget instanceof Budget
            ? $budget
            : Budget::query()->findOrFail($budget);
        $reserved = $this->sumReservations($budget, [BudgetReservation::STATUS_RESERVED]);
        $committed = $this->sumReservations($budget, [BudgetReservation::STATUS_COMMITTED]);

        return [
            'allocation' => $this->money($budget->amount),
            'reserved' => $reserved,
            'committed' => $committed,
            'available' => $this->availableAmount($budget),
        ];
    }

    public function resolveBudget(PurchaseRequest $purchaseRequest, ?int $year = null): ?Budget
    {
        if ($purchaseRequest->cost_center_id === null) {
            return null;
        }

        $costCenter = CostCenter::query()->find($purchaseRequest->cost_center_id);
        if (! $costCenter instanceof CostCenter) {
            return null;
        }

        return Budget::query()
            ->active()
            ->where('office_id', $costCenter->office_id)
            ->where('cost_center_id', $costCenter->getKey())
            ->where('year', $year ?? $this->requestYear($purchaseRequest))
            ->first();
    }

    public function resolveBudgetOwnerOfficeId(PurchaseRequest $purchaseRequest, ?int $year = null): int|string|null
    {
        if ($purchaseRequest->cost_center_id === null) {
            return $purchaseRequest->office_id;
        }

        return CostCenter::query()->whereKey($purchaseRequest->cost_center_id)->value('office_id')
            ?? $purchaseRequest->office_id;
    }

    public function resolveBudgetOwnerOffice(PurchaseRequest $purchaseRequest, ?int $year = null): ?Office
    {
        $officeId = $this->resolveBudgetOwnerOfficeId($purchaseRequest, $year);

        return $officeId === null ? null : Office::query()->find($officeId);
    }

    private function reviseLocked(
        Budget $budget,
        BudgetReservation $reservation,
        string $amount,
        string $reason,
        ?User $actor,
    ): BudgetReservation {
        $currentAmount = $this->money($reservation->amount);
        $availableWithoutCurrent = $this->add($this->availableAmount($budget), $currentAmount);
        if ($this->moneyCompare($availableWithoutCurrent, $amount) < 0) {
            $this->throwInsufficient($amount, $availableWithoutCurrent);
        }

        $before = $this->snapshot($reservation);
        $reservation->forceFill(['amount' => $amount])->save();
        $this->audit($reservation, $actor, 'revised', $before, $this->snapshot($reservation), $reason);

        return $reservation->fresh(['budget', 'purchaseRequest']);
    }

    private function throwInsufficient(string $requested, string $available): never
    {
        throw ValidationException::withMessages([
            'budget' => sprintf(
                'Insufficient available budget. Requested %s; available %s; shortfall %s.',
                $requested,
                $available,
                $this->subtract($requested, $available),
            ),
        ]);
    }

    private function assertFeature(?User $actor = null): void
    {
        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_BUDGETS, $actor);
    }

    private function authorizeBudget(Budget $budget, ?User $actor): void
    {
        if ($actor === null || Gate::forUser($actor)->allows('update', $budget)) {
            return;
        }

        throw new AuthorizationException('The actor is not authorized for this budget scope.');
    }

    private function reservationId(BudgetReservation|PurchaseRequest|int $reservation): int
    {
        if ($reservation instanceof BudgetReservation) {
            return (int) $reservation->getKey();
        }

        if ($reservation instanceof PurchaseRequest) {
            return (int) BudgetReservation::query()
                ->where('purchase_request_id', $reservation->getKey())
                ->value('id');
        }

        return $reservation;
    }

    /** @param list<string> $statuses */
    private function sumReservations(Budget $budget, array $statuses): string
    {
        return $this->money((string) BudgetReservation::query()
            ->where('budget_id', $budget->getKey())
            ->whereIn('status', $statuses)
            ->sum('amount'));
    }

    private function requestYear(PurchaseRequest $purchaseRequest): int
    {
        return (int) ($purchaseRequest->required_date?->year ?? date('Y'));
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for a budget reservation adjustment.',
            ]);
        }
    }

    /** @return array{amount: string, status: string} */
    private function snapshot(BudgetReservation $reservation): array
    {
        return [
            'amount' => $this->money($reservation->amount),
            'status' => (string) $reservation->status,
        ];
    }

    /** @param array{amount: string, status: string}|null $before */
    private function audit(
        BudgetReservation $reservation,
        ?User $actor,
        string $event,
        ?array $before,
        array $after,
        string $reason,
    ): void {
        $activity = activity('finance')
            ->performedOn($reservation)
            ->event($event)
            ->withProperties([
                'before' => $before,
                'after' => $after,
                'reason' => $reason,
                'budget_id' => $reservation->budget_id,
                'purchase_request_id' => $reservation->purchase_request_id,
            ]);

        if ($actor instanceof User) {
            $activity->causedBy($actor);
        }

        $activity->log('Budget reservation '.$event);
    }

    private function money(mixed $value): string
    {
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'amount' => 'A financial amount must be numeric.',
            ]);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, 2);
    }

    private function subtract(string $left, string $right): string
    {
        return bcsub($left, $right, 2);
    }

    private function moneyCompare(string $left, string $right): int
    {
        return bccomp($left, $right, 2);
    }
}
