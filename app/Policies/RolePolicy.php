<?php

namespace App\Policies;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;
use Spatie\Permission\Models\Role as SpatieRole;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.roles', $user) && app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function view(User $user, SpatieRole $role): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.roles', $user) && app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.roles', $user) && app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function update(User $user, SpatieRole $role): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.roles', $user) && app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }

    public function delete(User $user, SpatieRole $role): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.roles', $user) && app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }
}
