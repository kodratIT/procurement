<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcurementVariant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class ProcurementVariantPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $this->canManage($user);
    }

    public function view(User $user, ProcurementVariant $variant): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $variant);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $this->canManage($user);
    }

    public function update(User $user, ProcurementVariant $variant): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $this->view($user, $variant);
    }

    public function delete(User $user, ProcurementVariant $variant): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $this->view($user, $variant) && ! $variant->purchaseRequestItems()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && false;
    }

    public function deactivate(User $user, ProcurementVariant $variant): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && $variant->is_active && $this->view($user, $variant);
    }

    public function activate(User $user, ProcurementVariant $variant): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.variants', $user) && ! $variant->is_active
            && $variant->item()->availableForNewTransactions()->exists()
            && $this->view($user, $variant);
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
