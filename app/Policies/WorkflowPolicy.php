<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

final class WorkflowPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $this->manage($user) && ! $workflow->versions()->whereHas('approvalInstances')->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function activate(User $user, Workflow $workflow): bool
    {
        return $this->manage($user);
    }

    public function retire(User $user, Workflow $workflow): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
