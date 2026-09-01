<?php

namespace App\Policies;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\ProcurementPermissions;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, Activity $activity): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::VIEW);
    }

    public function exportActivity(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::EXPORT);
    }

    public function manageExportPresets(User $user): bool
    {
        return app(AuthorizationService::class)->allows($user, ProcurementPermissions::MANAGE_ROLES);
    }
}
