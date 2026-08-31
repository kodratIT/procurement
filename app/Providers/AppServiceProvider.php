<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\DepartureBatch;
use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\UmrahBatch;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Models\VendorItem;
use App\Policies\ActivityPolicy;
use App\Policies\ApprovalInstanceStepPolicy;
use App\Policies\ApproverDelegationPolicy;
use App\Policies\ApproverMappingPolicy;
use App\Policies\ContextPolicy;
use App\Policies\OfficePolicy;
use App\Policies\PilgrimPolicy;
use App\Policies\ProcurementCategoryPolicy;
use App\Policies\ProcurementFieldPolicy;
use App\Policies\ProcurementItemPolicy;
use App\Policies\ProcurementUnitPolicy;
use App\Policies\ProcurementVariantPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\UmrahBatchPolicy;
use App\Policies\UserAssignmentPolicy;
use App\Policies\VendorItemPolicy;
use App\Policies\VendorPolicy;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ApprovalInstanceStep::class, ApprovalInstanceStepPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(ProcurementField::class, ProcurementFieldPolicy::class);
        Gate::policy(ProcurementCategory::class, ProcurementCategoryPolicy::class);
        Gate::policy(ProcurementItem::class, ProcurementItemPolicy::class);
        Gate::policy(ProcurementUnit::class, ProcurementUnitPolicy::class);
        Gate::policy(ProcurementVariant::class, ProcurementVariantPolicy::class);
        Gate::policy(ApproverMapping::class, ApproverMappingPolicy::class);
        Gate::policy(ApproverDelegation::class, ApproverDelegationPolicy::class);
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SpatieRole::class, RolePolicy::class);
        Gate::policy(UserAssignment::class, UserAssignmentPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Pilgrim::class, PilgrimPolicy::class);
        Gate::policy(UmrahBatch::class, UmrahBatchPolicy::class);
        Gate::policy(VendorItem::class, VendorItemPolicy::class);

        foreach ([DepartureBatch::class] as $model) {
            Gate::policy($model, ContextPolicy::class);
        }

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
