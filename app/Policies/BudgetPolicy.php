<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->canManageRecord($user, $budget);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->canManageRecord($user, $budget);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_FINANCE);
    }

    private function canManageRecord(User $user, Budget $budget): bool
    {
        return app(AuthorizationService::class)->canManageRecord($user, ProcurementPermissions::MANAGE_FINANCE, $budget);
    }
}
