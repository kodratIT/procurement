<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && $this->canManage($user);
    }

    public function view(User $user, Budget $budget): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && $this->canManageRecord($user, $budget);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && $this->canManage($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && $this->canManageRecord($user, $budget);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && false;
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('organization-finance.budgets', $user) && false;
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_FINANCE);
    }

    private function canManageRecord(User $user, Budget $budget): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_FINANCE, $budget);
    }
}
