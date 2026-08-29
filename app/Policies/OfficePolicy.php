<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Carbon;

class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->assignments()->where('is_active', true)->whereDate('valid_from', '<=', Carbon::today())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', Carbon::today()))
            ->whereHas('office', fn ($query) => $query->where('is_active', true)->whereNull('disabled_at'))
            ->exists();
    }

    public function view(User $user, Office $office): bool
    {
        return $user->assignments()->where('office_id', $office->getKey())->where('is_active', true)
            ->whereDate('valid_from', '<=', Carbon::today())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', Carbon::today()))
            ->whereHas('office', fn ($query) => $query->where('is_active', true)->whereNull('disabled_at'))
            ->exists();
    }

    public function select(User $user, Office $office): bool
    {
        return $this->view($user, $office);
    }
}
