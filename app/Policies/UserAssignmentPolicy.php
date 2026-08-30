<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ProcurementPermissions;

class UserAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, UserAssignment $assignment): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, UserAssignment $assignment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, UserAssignment $assignment): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_USERS);
    }
}
