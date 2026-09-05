<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class VendorPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->canManageRecord($user, ProcurementPermissions::VIEW, $vendor);
    }

    public function viewSensitiveData(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $vendor);
    }

    public function export(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->authorization->allows($user, ProcurementPermissions::EXPORT);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->canManage($user);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->canManageRecord($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $this->canManageRecord($user, $vendor) && ! $vendor->items()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && false;
    }

    public function deactivate(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && $vendor->is_active && $this->canManageRecord($user, $vendor);
    }

    public function activate(User $user, Vendor $vendor): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.vendors', $user) && ! $vendor->is_active && $this->canManageRecord($user, $vendor);
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }

    private function canManageRecord(User $user, Vendor $vendor): bool
    {
        return $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $vendor);
    }
}
