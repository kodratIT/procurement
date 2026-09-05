<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VendorItem;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class VendorItemPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, VendorItem $vendorItem): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->canManageRecord($user, ProcurementPermissions::VIEW, $vendorItem);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->canManage($user);
    }

    public function update(User $user, VendorItem $vendorItem): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->canManageRecord($user, $vendorItem);
    }

    public function delete(User $user, VendorItem $vendorItem): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && false;
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && false;
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }

    private function canManageRecord(User $user, VendorItem $vendorItem): bool
    {
        return $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $vendorItem);
    }
}
