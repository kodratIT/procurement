<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use App\Services\WorkflowStageService;
use App\Support\ProcurementPermissions;

final class PurchaseRequestPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $this->authorization->canView($user);
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $this->authorization->canView($user, $request);
    }

    public function viewTimeline(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $this->authorization->canView($user, $request);
    }

    public function submit(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $request->isCorrectable()
            && $request->requester_id === $user->getKey()
            && $this->authorization->canMutate($user, $request, ProcurementPermissions::SUBMIT);
    }

    public function return(User $user, PurchaseRequest $request): bool
    {
        $allowed = [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW];
        $isDynamic = app(WorkflowStageService::class)->isDynamicStage($request->status, $request);
        $inAllowed = in_array($request->status, $allowed, true) || $isDynamic;

        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $inAllowed
            && $request->requester_id !== $user->getKey()
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function review(User $user, PurchaseRequest $request): bool
    {
        $allowed = [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW];
        $isDynamic = app(WorkflowStageService::class)->isDynamicStage($request->status, $request);
        $inAllowed = in_array($request->status, $allowed, true) || $isDynamic;

        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $inAllowed
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function handoff(User $user, PurchaseRequest $request): bool
    {
        $allowed = [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW];
        $isDynamic = app(WorkflowStageService::class)->isDynamicStage($request->status, $request);
        $inAllowed = in_array($request->status, $allowed, true) || $isDynamic;

        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $inAllowed
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function forward(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $request->status === PurchaseRequest::STATUS_SUBMITTED
            && $this->authorization->canUpdate($user, $request, true);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $this->authorization->canCreate($user);
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $request->isCorrectable() && $this->authorization->canUpdate($user, $request, true);
    }

    public function delete(User $user, PurchaseRequest $request): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $request->isDraft() && $this->authorization->canDelete($user, $request, true);
    }

    public function deleteAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.requests', $user) && $this->authorization->canDelete($user);
    }
}
