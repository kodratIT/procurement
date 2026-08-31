<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DistributionItem;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class DistributionItemPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, DistributionItem $item): bool
    {
        return $this->allowsOnBatch($user, $item, ProcurementPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::CREATE);
    }

    public function update(User $user, DistributionItem $item): bool
    {
        return false;
    }

    public function delete(User $user, DistributionItem $item): bool
    {
        return false;
    }

    private function allowsOnBatch(User $user, DistributionItem $item, string $permission): bool
    {
        $item->loadMissing('distribution.batch');

        return $item->distribution?->batch !== null
            && $this->authorization->allows($user, $permission, $item->distribution->batch);
    }
}
