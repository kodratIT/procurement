<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApproverMapping;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

final class ApproverMappingPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, ApproverMapping $mapping): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, ApproverMapping $mapping): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, ApproverMapping $mapping): bool
    {
        return $this->manage($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function manage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
