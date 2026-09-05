<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApproverDelegation;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Support\ProcurementPermissions;

final class ApproverDelegationPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && $this->manage($user);
    }

    public function view(User $user, ApproverDelegation $delegation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && $this->manage($user);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && $this->manage($user);
    }

    public function update(User $user, ApproverDelegation $delegation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && $this->manage($user);
    }

    public function delete(User $user, ApproverDelegation $delegation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && $this->manage($user);
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('settings.approver-delegations', $user) && false;
    }

    private function manage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
