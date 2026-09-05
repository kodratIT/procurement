<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\DomainTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class PurchaseRequestCancellationService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly PurchaseRequestTimeline $timeline,
        private readonly FeatureModuleService $featureModules,
    ) {}

    public function canCancel(PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequest::STATUS_SUBMITTED
            && ! $request->approvalInstances()->whereIn('status', ['pending', 'in_progress'])->exists();
    }

    public function cancel(PurchaseRequest $request, ?User $actor = null, ?string $notes = null): PurchaseRequest
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated user is required.');
        }

        if (blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'Alasan pembatalan wajib diisi.']);
        }

        if (! $this->canCancel($request)) {
            throw ValidationException::withMessages(['status' => 'PR hanya dapat dibatalkan sebelum masuk ke tahap persetujuan.']);
        }

        return $this->transaction->run(
            'cancel purchase request',
            function () use ($request, $actor, $notes): PurchaseRequest {
                $locked = PurchaseRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($request->getKey());
                $fromStatus = $locked->status;

                if (! $this->canCancel($locked)) {
                    throw ValidationException::withMessages(['status' => 'PR hanya dapat dibatalkan sebelum masuk ke tahap persetujuan.']);
                }

                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                    'status' => PurchaseRequest::STATUS_CANCELLED,
                    'updated_at' => now(),
                ]);
                $locked->refresh();

                $this->timeline->record($locked, $actor, $fromStatus, PurchaseRequest::STATUS_CANCELLED, 'cancelled', 'cancel', $notes);

                activity('procurement')->performedOn($locked)->causedBy($actor)->event('cancelled')->withProperties([
                    'before' => ['status' => $fromStatus],
                    'after' => ['status' => PurchaseRequest::STATUS_CANCELLED],
                    'reason' => $notes,
                ])->log('Purchase request cancelled');

                // Cancel any pending approval instances
                foreach ($locked->approvalInstances()->whereIn('status', ['pending', 'in_progress'])->get() as $instance) {
                    $instance->forceFill(['status' => 'cancelled'])->save();
                    foreach ($instance->steps()->whereIn('status', ['pending', 'queued'])->get() as $step) {
                        $step->forceFill(['status' => 'skipped', 'note' => 'Skipped due to cancellation.', 'completed_at' => now()])->save();
                    }
                }

                return $locked;
            },
            ['purchase_request_id' => $request->getKey(), 'actor_id' => $actor->getKey()],
        );
    }
}
