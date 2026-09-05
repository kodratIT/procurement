<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SampleShipment;
use App\Models\User;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class SampleShipmentPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $this->authorization->canView($user);
    }

    public function view(User $user, SampleShipment $shipment): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $this->authorization->canView($user, $shipment)
            || $this->authorization->canView($user, ['office_id' => $shipment->receiver_office_id]);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $this->authorization->canCreate($user);
    }

    public function update(User $user, SampleShipment $shipment): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && ! $shipment->isTerminal()
            && $this->authorization->canUpdate($user, $shipment, true);
    }

    public function delete(User $user, SampleShipment $shipment): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $shipment->statusValue() === SampleShipment::STATUS_DRAFT
            && $this->authorization->canDelete($user, $shipment, true);
    }

    public function submit(User $user, SampleShipment $shipment): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $shipment->statusValue() === SampleShipment::STATUS_DRAFT
            && $this->authorization->can($user, ProcurementPermissions::SUBMIT, $shipment);
    }

    public function approve(User $user, SampleShipment $shipment): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('umrah-operations.sample-shipments', $user) && $shipment->statusValue() === SampleShipment::STATUS_PROCUREMENT_REVIEW
            && $this->authorization->can($user, ProcurementPermissions::APPROVE, $shipment);
    }
}
