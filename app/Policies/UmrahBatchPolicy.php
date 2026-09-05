<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;

final class UmrahBatchPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && $this->authorization->canView($user);
    }

    public function view(User $user, UmrahBatch $batch): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && $this->authorization->canView($user, $batch);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && $this->authorization->canCreate($user);
    }

    public function update(User $user, UmrahBatch $batch): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && $this->authorization->canUpdate($user, $batch, true);
    }

    public function delete(User $user, UmrahBatch $batch): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && false;
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && false;
    }

    public function deactivate(User $user, UmrahBatch $batch): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && $batch->is_active && $this->authorization->canUpdate($user, $batch, true);
    }

    public function activate(User $user, UmrahBatch $batch): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.umrah-batches', $user) && ! $batch->is_active && $this->authorization->canUpdate($user, $batch, true);
    }
}
