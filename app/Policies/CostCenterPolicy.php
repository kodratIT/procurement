<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\User;
use App\Support\ProcurementPermissions;

class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, CostCenter $costCenter): bool
    {
        return $this->canManage($user) && $costCenter->is_active;
    }

    private function canManage(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
