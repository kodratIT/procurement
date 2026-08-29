<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ProcurementPermissions;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ProcurementPermissions::VIEW);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(ProcurementPermissions::VIEW);
    }

    public function exportActivity(User $user): bool
    {
        return $user->can(ProcurementPermissions::EXPORT);
    }

    public function manageExportPresets(User $user): bool
    {
        return $user->can(ProcurementPermissions::MANAGE_ROLES);
    }
}
