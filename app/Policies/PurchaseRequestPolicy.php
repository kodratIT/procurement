<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class PurchaseRequestPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->canView($user);
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $this->authorization->canView($user, $request);
    }

    public function viewTimeline(User $user, PurchaseRequest $request): bool
    {
        return $this->authorization->canView($user, $request);
    }

    public function submit(User $user, PurchaseRequest $request): bool
    {
        return $request->isCorrectable()
            && $request->requester_id === $user->getKey()
            && $this->authorization->canMutate($user, $request, ProcurementPermissions::SUBMIT);
    }

    public function return(User $user, PurchaseRequest $request): bool
    {
        return in_array($request->status, [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW], true)
            && $request->requester_id !== $user->getKey()
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function review(User $user, PurchaseRequest $request): bool
    {
        return in_array($request->status, [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW], true)
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function forward(User $user, PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequest::STATUS_SUBMITTED
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function create(User $user): bool
    {
        return $this->authorization->canCreate($user);
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        return $request->isCorrectable() && $this->authorization->canUpdate($user, $request, true);
    }

    public function delete(User $user, PurchaseRequest $request): bool
    {
        return $request->isDraft() && $this->authorization->canDelete($user, $request, true);
    }

    public function deleteAny(User $user): bool
    {
        return $this->authorization->canDelete($user);
    }
}
