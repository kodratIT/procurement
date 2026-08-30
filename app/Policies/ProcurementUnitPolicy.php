<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcurementUnit;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

final class ProcurementUnitPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, ProcurementUnit $unit): bool
    {
        return $this->authorization->canManageRecord($user, ProcurementPermissions::MANAGE_MASTER_DATA, $unit);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ProcurementUnit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function delete(User $user, ProcurementUnit $unit): bool
    {
        return $this->view($user, $unit)
            && ! $unit->items()->exists()
            && ! $unit->purchaseRequestItems()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, ProcurementUnit $unit): bool
    {
        return $unit->is_active && $this->view($user, $unit);
    }

    public function activate(User $user, ProcurementUnit $unit): bool
    {
        return ! $unit->is_active && $this->view($user, $unit);
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
