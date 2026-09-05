<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowVersion;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowVersionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ViewAny:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::MANAGE_MASTER_DATA));

    }

    public function view(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'View:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowVersion));

    }

    public function create(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Create:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::MANAGE_MASTER_DATA));

    }

    public function update(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Update:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowVersion));

    }

    public function delete(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Delete:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowVersion));

    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'DeleteAny:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restore(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Restore:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowVersion));

    }

    public function forceDelete(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'ForceDelete:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowVersion));

    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ForceDeleteAny:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'RestoreAny:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function replicate(AuthUser $authUser, WorkflowVersion $workflowVersion): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Replicate:WorkflowVersion', $workflowVersion) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowVersion));

    }

    public function reorder(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-versions', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Reorder:WorkflowVersion') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }
}
