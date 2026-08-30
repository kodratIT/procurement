<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class UserAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, UserAssignment $assignment): bool
    {
        return $this->canManageRecord($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, UserAssignment $assignment): bool
    {
        return $this->canManageRecord($user, $assignment);
    }

    public function delete(User $user, UserAssignment $assignment): bool
    {
        return $this->canManageRecord($user, $assignment);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_USERS);
    }

    private function canManageRecord(User $user, UserAssignment $assignment): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_USERS, $assignment);
    }
}
