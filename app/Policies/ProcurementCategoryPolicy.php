<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcurementCategory;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class ProcurementCategoryPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $this->canManage($user);
    }

    public function view(User $user, ProcurementCategory $category): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $this->authorization->canManageRecord(
            $user,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            $category,
        );
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $this->canManage($user);
    }

    public function update(User $user, ProcurementCategory $category): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $this->authorization->canManageRecord(
            $user,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            $category,
        );
    }

    public function delete(User $user, ProcurementCategory $category): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $this->authorization->canManageRecord(
            $user,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            $category,
        ) && ! $category->purchaseRequests()->exists()
            && ! $category->items()->exists()
            && ! $category->fields()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && false;
    }

    public function deactivate(User $user, ProcurementCategory $category): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && $category->is_active && $this->authorization->canManageRecord(
            $user,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            $category,
        );
    }

    public function activate(User $user, ProcurementCategory $category): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.categories', $user) && ! $category->is_active && $this->authorization->canManageRecord(
            $user,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            $category,
        );
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
