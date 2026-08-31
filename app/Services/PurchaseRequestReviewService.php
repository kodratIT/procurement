<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PurchaseRequestReviewService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly PurchaseRequestTimeline $timeline,
        private readonly AccessContextService $context,
    ) {}

    public function returnToRequester(
        PurchaseRequest $request,
        string $note,
        ?User $reviewer = null,
    ): PurchaseRequest {
        $reviewer ??= auth()->user();

        if (! $reviewer instanceof User || ! $reviewer->is_active) {
            throw new AuthorizationException('An active authenticated reviewer is required.');
        }

        if (blank($note)) {
            throw ValidationException::withMessages([
                'note' => 'A correction note is required when returning a purchase request.',
            ]);
        }

        Gate::forUser($reviewer)->authorize('return', $request);

        if ($request->requester_id === $reviewer->getKey()) {
            throw new AuthorizationException('The requester cannot return their own purchase request for correction.');
        }

        $assignment = $this->context->assignment();
        if ($assignment === null || ! $this->context->can(ProcurementPermissions::UPDATE)) {
            throw new AuthorizationException('An active procurement review context is required.');
        }

        return $this->transaction->run(
            'return purchase request for correction',
            function () use ($request, $reviewer, $note): PurchaseRequest {
                $locked = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->getKey());
                $fromStatus = $locked->status;

                if (! in_array($fromStatus, [PurchaseRequestStatus::Submitted->value, PurchaseRequestStatus::ProcurementReview->value], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a submitted purchase request can be returned for correction.',
                    ]);
                }

                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                    'status' => PurchaseRequestStatus::Returned->value,
                    'updated_at' => now(),
                ]);
                $locked->refresh();

                $this->timeline->record(
                    $locked,
                    $reviewer,
                    $fromStatus,
                    PurchaseRequestStatus::Returned->value,
                    'returned',
                    'return',
                    $note,
                );
                $this->audit($locked, $reviewer, $fromStatus, $note);

                return $locked;
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $reviewer->getKey(),
            ],
        );
    }

    private function audit(PurchaseRequest $request, User $actor, string $fromStatus, string $note): void
    {
        activity('procurement')
            ->performedOn($request)
            ->causedBy($actor)
            ->event('returned')
            ->withProperties([
                'before' => ['status' => $fromStatus],
                'after' => ['status' => PurchaseRequestStatus::Returned->value],
                'note' => $note,
                'office_id' => $request->office_id,
                'branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
            ])
            ->log('Purchase request returned for correction');
    }
}
