<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Office $office): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $office);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Office $office): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $office);
    }

    public function delete(User $user, Office $office): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, Office $office): bool
    {
        return $office->is_active
            && app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $office);
    }

    public function select(User $user, Office $office): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::VIEW, $office)
            && $office->is_active
            && $office->disabled_at === null;
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
