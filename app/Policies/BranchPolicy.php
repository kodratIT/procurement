<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Support\ProcurementPermissions;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->canManage($user);
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
        return $this->canManage($user) && $branch->is_active;
    }

    private function canManage(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
