<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\User;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class PurchaseRequestTimeline
{
    public function __construct(private readonly AccessContextService $context) {}

    /** @return Collection<int, PurchaseRequestStatusHistory> */
    public function for(PurchaseRequest $request, ?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->is_active) {
            throw new AuthorizationException('An active authenticated user is required.');
        }

        Gate::forUser($user)->authorize('viewTimeline', $request);

        return $request->statusHistories()
            ->with(['actor', 'office', 'branch', 'department', 'role'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Record a process event with the actor's immutable access context.
     * This method is called only by domain services inside their transaction.
     */
    public function record(
        PurchaseRequest $request,
        ?User $actor,
        ?string $fromStatus,
        string $toStatus,
        string $event,
        ?string $decision = null,
        ?string $note = null,
        array $context = [],
    ): PurchaseRequestStatusHistory {
        $accessContext = $this->context->snapshot();
        $historyContext = [
            ...$context,
            'access_context' => $accessContext,
            'requester_id' => $request->requester_id,
            'office_id' => $request->office_id,
            'branch_id' => $request->branch_id,
            'department_id' => $request->department_id,
            'permission' => ProcurementPermissions::VIEW,
        ];

        return $request->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event' => $event,
            'decision' => $decision,
            'note' => $note,
            'actor_id' => $actor?->getKey(),
            'office_id' => $accessContext['office_id'] ?? $request->office_id,
            'branch_id' => $accessContext['branch_id'] ?? $request->branch_id,
            'department_id' => $accessContext['department_id'] ?? $request->department_id,
            'role_id' => $accessContext['role_id'] ?? null,
            'context' => $historyContext,
        ]);
    }
}
