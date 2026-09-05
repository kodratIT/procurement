<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PilgrimDistributionItem;
use App\Models\User;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class PilgrimDistributionItemPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, PilgrimDistributionItem $allocation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && $this->allowsOnBatch($user, $allocation, ProcurementPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && $this->authorization->allows($user, ProcurementPermissions::RECEIVE);
    }

    public function update(User $user, PilgrimDistributionItem $allocation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && $this->allowsOnBatch($user, $allocation, ProcurementPermissions::RECEIVE);
    }

    public function attachEvidence(User $user, PilgrimDistributionItem $allocation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && $this->update($user, $allocation);
    }

    public function delete(User $user, PilgrimDistributionItem $allocation): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.distributions', $user) && false;
    }

    private function allowsOnBatch(User $user, PilgrimDistributionItem $allocation, string $permission): bool
    {
        $allocation->loadMissing('distributionItem.distribution.batch');

        return $allocation->distributionItem?->distribution?->batch !== null
            && $this->authorization->allows($user, $permission, $allocation->distributionItem->distribution->batch);
    }
}
