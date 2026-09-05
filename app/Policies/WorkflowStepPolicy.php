<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowStep;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowStepPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ViewAny:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::MANAGE_MASTER_DATA));

    }

    public function view(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'View:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowStep));

    }

    public function create(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Create:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::MANAGE_MASTER_DATA));

    }

    public function update(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Update:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowStep));

    }

    public function delete(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Delete:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::MANAGE_MASTER_DATA, $workflowStep));

    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'DeleteAny:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restore(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Restore:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowStep));

    }

    public function forceDelete(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'ForceDelete:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowStep));

    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'ForceDeleteAny:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'RestoreAny:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }

    public function replicate(AuthUser $authUser, WorkflowStep $workflowStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->canManageRecord($authUser, 'Replicate:WorkflowStep', $workflowStep) || app(AuthorizationService::class)->canManageRecord($authUser, ProcurementPermissions::VIEW, $workflowStep));

    }

    public function reorder(AuthUser $authUser): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('master-data.workflow-stages', $authUser) && (app(AuthorizationService::class)->allows($authUser, 'Reorder:WorkflowStep') || app(AuthorizationService::class)->allows($authUser, ProcurementPermissions::VIEW));

    }
}
