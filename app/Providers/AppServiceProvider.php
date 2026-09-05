<?php

namespace App\Providers;

use App\Contracts\BudgetCheck;
use App\Models\Activity;
use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\Attachment;
use App\Models\Budget;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\PilgrimDistributionItem;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SampleShipment;
use App\Models\SampleShipmentReceipt;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Models\VendorItem;
use App\Policies\ActivityPolicy;
use App\Policies\ApprovalInstanceStepPolicy;
use App\Policies\ApproverDelegationPolicy;
use App\Policies\ApproverMappingPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ContextPolicy;
use App\Policies\DistributionItemPolicy;
use App\Policies\DistributionPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OfficePolicy;
use App\Policies\PilgrimDistributionItemPolicy;
use App\Policies\PilgrimPolicy;
use App\Policies\ProcurementCategoryPolicy;
use App\Policies\ProcurementFieldPolicy;
use App\Policies\ProcurementItemPolicy;
use App\Policies\ProcurementUnitPolicy;
use App\Policies\ProcurementVariantPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\SampleShipmentPolicy;
use App\Policies\UmrahBatchPolicy;
use App\Policies\UserAssignmentPolicy;
use App\Policies\VendorItemPolicy;
use App\Policies\VendorPolicy;
use App\Services\AccessContextService;
use App\Services\BudgetReservationService;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use BezhanSalleh\FilamentShield\Commands\GenerateCommand;
use BezhanSalleh\FilamentShield\Commands\InstallCommand;
use BezhanSalleh\FilamentShield\Commands\PublishCommand;
use BezhanSalleh\FilamentShield\Commands\SeederCommand;
use BezhanSalleh\FilamentShield\Commands\SetupCommand;
use BezhanSalleh\FilamentShield\Commands\SuperAdminCommand;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role as SpatieRole;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AccessContextService::class);
        $this->app->scoped(MultiOfficeAuthorization::class);
        $this->app->scoped(BudgetCheck::class, fn ($app): BudgetReservationService => $app->make(BudgetReservationService::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentShield::enforcePolicies();
        FilamentShield::prohibitDestructiveCommands(app()->isProduction());
        GenerateCommand::prohibit(app()->isProduction());
        InstallCommand::prohibit(app()->isProduction());
        PublishCommand::prohibit(app()->isProduction());
        SeederCommand::prohibit(app()->isProduction());
        SetupCommand::prohibit(app()->isProduction());
        SuperAdminCommand::prohibit(app()->isProduction());

        Gate::before(function (?User $user, string $ability, array $arguments): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            $featureService = app(FeatureModuleService::class);
            $featureKey = $featureService->featureForSubject($arguments[0] ?? null);
            if ($featureKey === null || $featureService->isSuperAdminForAuthorization($user)) {
                return null;
            }

            return $featureService->isEnabled($featureKey) ? null : false;
        });
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Distribution::class, DistributionPolicy::class);
        Gate::policy(DistributionItem::class, DistributionItemPolicy::class);
        Gate::policy(PilgrimDistributionItem::class, PilgrimDistributionItemPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(ApprovalInstanceStep::class, ApprovalInstanceStepPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(ProcurementField::class, ProcurementFieldPolicy::class);
        Gate::policy(ProcurementCategory::class, ProcurementCategoryPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(ProcurementItem::class, ProcurementItemPolicy::class);
        Gate::policy(ProcurementUnit::class, ProcurementUnitPolicy::class);
        Gate::policy(ProcurementVariant::class, ProcurementVariantPolicy::class);
        Gate::policy(ApproverMapping::class, ApproverMappingPolicy::class);
        Gate::policy(ApproverDelegation::class, ApproverDelegationPolicy::class);
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SpatieRole::class, RolePolicy::class);
        Gate::policy(UserAssignment::class, UserAssignmentPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Pilgrim::class, PilgrimPolicy::class);
        Gate::policy(UmrahBatch::class, UmrahBatchPolicy::class);
        Gate::policy(VendorItem::class, VendorItemPolicy::class);
        Gate::policy(SampleShipment::class, SampleShipmentPolicy::class);
        Gate::policy(SampleShipmentReceipt::class, ContextPolicy::class);

        Activity::creating(function (Activity $activity): void {
            $context = app(AccessContextService::class)->snapshot();

            if ($context === null) {
                return;
            }

            $activity->properties = ($activity->properties ?? collect())->put('access_context', array_merge(
                $context,
                ['role' => app(AccessContextService::class)->roleName()],
            ));
        });
    }
}
