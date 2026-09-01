<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcurementField;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

final class ProcurementFieldPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, ProcurementField $field): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ProcurementField $field): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, ProcurementField $field): bool
    {
        return $this->canManage($user) && ! $field->values()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, ProcurementField $field): bool
    {
        return $field->is_active && $this->canManage($user);
    }

    public function activate(User $user, ProcurementField $field): bool
    {
        return ! $field->is_active && $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
