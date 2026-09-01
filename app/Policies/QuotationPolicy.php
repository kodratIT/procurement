<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class QuotationPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->requestIsAllowed($user, $this->requestFor($quotation), ProcurementPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::CREATE);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SUBMITTED], true)
            && $this->requestIsAllowed($user, $this->requestFor($quotation), ProcurementPermissions::UPDATE);
    }

    public function recommend(User $user, Quotation|PurchaseRequest $subject): bool
    {
        $request = $subject instanceof PurchaseRequest ? $subject : $this->requestFor($subject);

        return $request instanceof PurchaseRequest
            && in_array($request->status, [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW], true)
            && $this->requestIsAllowed($user, $request, ProcurementPermissions::UPDATE);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->status === Quotation::STATUS_DRAFT
            && $this->requestIsAllowed($user, $this->requestFor($quotation), ProcurementPermissions::UPDATE);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function requestFor(Quotation $quotation): ?PurchaseRequest
    {
        return $quotation->purchaseRequest
            ?? $quotation->purchaseRequest()->withoutGlobalScopes()->first();
    }

    private function requestIsAllowed(User $user, ?PurchaseRequest $request, string $permission): bool
    {
        return $request instanceof PurchaseRequest
            && $this->authorization->allows($user, $permission, $request);
    }
}
