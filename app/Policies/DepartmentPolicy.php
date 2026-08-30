<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Support\ProcurementPermissions;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Department $department): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, Department $department): bool
    {
        return $this->canManage($user) && $department->is_active;
    }

    private function canManage(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
