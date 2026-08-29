<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ProcurementPermissions;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }
}
