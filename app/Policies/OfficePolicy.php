<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;
use App\Services\ActiveOfficeContext;
use App\Support\ProcurementPermissions;
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

    /**
     * Whether the user may act across offices at all (the escape hatch).
     */
    public function viewAllOffices(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_USERS);
    }

    /**
     * Whether the user may switch to the given office via the context service.
     */
    public function switch(User $user, Office $office): bool
    {
        return app(ActiveOfficeContext::class)->hasAccess($office);
    }
}
