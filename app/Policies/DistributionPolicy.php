<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Distribution;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class DistributionPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, Distribution $distribution): bool
    {
        $distribution->loadMissing('batch');

        return $distribution->batch !== null
            && $this->authorization->allows($user, ProcurementPermissions::VIEW, $distribution->batch);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::CREATE);
    }

    public function update(User $user, Distribution $distribution): bool
    {
        return false;
    }

    public function delete(User $user, Distribution $distribution): bool
    {
        return false;
    }
}
