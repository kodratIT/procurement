<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ViewAny:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function view(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'View:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $purchaseOrder));

    }

    public function create(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Create:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::CREATE));

    }

    public function update(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Update:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::UPDATE, $purchaseOrder));

    }

    public function delete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Delete:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::DELETE, $purchaseOrder));

    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'DeleteAny:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restore(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Restore:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $purchaseOrder));

    }

    public function forceDelete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'ForceDelete:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $purchaseOrder));

    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ForceDeleteAny:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'RestoreAny:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function replicate(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Replicate:PurchaseOrder', $purchaseOrder) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $purchaseOrder));

    }

    public function reorder(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.purchase-orders', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Reorder:PurchaseOrder') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }
}
