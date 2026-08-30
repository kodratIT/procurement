<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canManageRecord($user, $branch);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->canManageRecord($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, Branch $branch): bool
    {
        return $branch->is_active && $this->canManageRecord($user, $branch);
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }

    private function canManageRecord(User $user, Branch $branch): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $branch);
    }
}
