<?php

namespace App\Policies;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function view(User $user, Role $role): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function update(User $user, Role $role): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function delete(User $user, Role $role): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }
}
