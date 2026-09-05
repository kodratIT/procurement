<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class ApprovalInstanceStepPolicy
{
    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('approvals.approval-inbox', $user) && $user->is_active
            && UserAssignment::query()
                ->currentlyActive()
                ->where('user_id', $user->getKey())
                ->get()
                ->contains(fn (UserAssignment $assignment): bool => $assignment->allows(ProcurementPermissions::APPROVE));
    }

    public function view(User $user, ApprovalInstanceStep $approvalInstanceStep): bool
    {
        if (! $user->is_active || ! $approvalInstanceStep->approvalInstance?->isActive()) {
            return app(FeatureModuleService::class)->featureIsAvailable('approvals.approval-inbox', $user) && false;
        }

        $delegated = ApproverDelegation::query()
            ->activeAt()
            ->where('delegator_id', $approvalInstanceStep->approver_id)
            ->where('delegate_id', $user->getKey())
            ->exists();

        if ((int) $approvalInstanceStep->approver_id !== (int) $user->getKey() && ! $delegated) {
            return false;
        }

        return UserAssignment::query()
            ->with('assignedRole')
            ->currentlyActive()
            ->where('user_id', $user->getKey())
            ->where('office_id', $approvalInstanceStep->office_id)
            ->get()
            ->contains(fn (UserAssignment $assignment): bool => ($assignment->branch_id === null || (int) $assignment->branch_id === (int) $approvalInstanceStep->branch_id)
                && ($assignment->department_id === null || (int) $assignment->department_id === (int) $approvalInstanceStep->department_id)
                && $assignment->allows(ProcurementPermissions::APPROVE));
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('approvals.approval-inbox', $user) && false;
    }

    public function update(User $user, ApprovalInstanceStep $approvalInstanceStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('approvals.approval-inbox', $user) && false;
    }

    public function delete(User $user, ApprovalInstanceStep $approvalInstanceStep): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('approvals.approval-inbox', $user) && false;
    }
}
