<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class InvoicePolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && $this->authorization->allows($user, ProcurementPermissions::VIEW, $invoice);
    }

    public function create(User $user): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && $this->authorization->allows($user, ProcurementPermissions::MANAGE_FINANCE);
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && $this->authorization->allows($user, ProcurementPermissions::MANAGE_FINANCE, $invoice);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && $this->authorization->allows($user, ProcurementPermissions::MANAGE_FINANCE, $invoice);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return app(FeatureModuleService::class)->featureIsAvailable('procurement.invoices', $user) && false;
    }
}
