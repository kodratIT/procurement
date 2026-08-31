<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;

final class PurchaseRequestPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->canView($user);
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $request->isDraft() && $this->authorization->canView($user, $request);
    }

    public function create(User $user): bool
    {
        return $this->authorization->canCreate($user);
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        return $request->isDraft() && $this->authorization->canUpdate($user, $request, true);
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
