<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcurementItem;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class ProcurementItemPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $this->canManage($user);
    }

    public function view(User $user, ProcurementItem $item): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $item);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $this->canManage($user);
    }

    public function update(User $user, ProcurementItem $item): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $this->view($user, $item);
    }

    public function delete(User $user, ProcurementItem $item): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $this->view($user, $item)
            && ! $item->variants()->exists()
            && ! $item->purchaseRequestItems()->exists()
            && ! $item->vendorItems()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && false;
    }

    public function deactivate(User $user, ProcurementItem $item): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && $item->is_active && $this->view($user, $item);
    }

    public function activate(User $user, ProcurementItem $item): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.items', $user) && ! $item->is_active
            && $item->category()->active()->exists()
            && $item->unit()->active()->exists()
            && $this->view($user, $item);
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
