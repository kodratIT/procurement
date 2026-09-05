<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && $this->canManage($user);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && $this->canManageRecord($user, $costCenter);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && $this->canManage($user);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && $this->canManageRecord($user, $costCenter);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && false;
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && false;
    }

    public function deactivate(User $user, CostCenter $costCenter): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.cost-centers', $user) && $costCenter->is_active && $this->canManageRecord($user, $costCenter);
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }

    private function canManageRecord(User $user, CostCenter $costCenter): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $costCenter);
    }
}
