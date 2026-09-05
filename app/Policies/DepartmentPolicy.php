<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && $this->canManage($user);
    }

    public function view(User $user, Department $department): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && $this->canManageRecord($user, $department);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && $this->canManage($user);
    }

    public function update(User $user, Department $department): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && $this->canManageRecord($user, $department);
    }

    public function delete(User $user, Department $department): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && false;
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && false;
    }

    public function deactivate(User $user, Department $department): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.departments', $user) && $department->is_active && $this->canManageRecord($user, $department);
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }

    private function canManageRecord(User $user, Department $department): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $department);
    }
}
