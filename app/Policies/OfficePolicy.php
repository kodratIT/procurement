<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;
use App\Support\ProcurementPermissions;

class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Office $office): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Office $office): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Office $office): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, Office $office): bool
    {
        return $this->canManage($user) && $office->is_active;
    }

    public function select(User $user, Office $office): bool
    {
        return $user->assignments()
            ->where('office_id', $office->getKey())
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()))
            ->whereHas('office', fn ($query) => $query->where('is_active', true)->whereNull('disabled_at'))
            ->exists();
    }

    private function canManage(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_MASTER_DATA);
    }
}
