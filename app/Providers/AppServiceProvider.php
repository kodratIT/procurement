<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DepartureBatch;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\Role;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Policies\ActivityPolicy;
use App\Policies\BranchPolicy;
use App\Policies\ContextPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\OfficePolicy;
use App\Policies\ProcurementCategoryPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserAssignmentPolicy;
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
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(CostCenter::class, CostCenterPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(ProcurementCategory::class, ProcurementCategoryPolicy::class);
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SpatieRole::class, RolePolicy::class);
        Gate::policy(UserAssignment::class, UserAssignmentPolicy::class);

        foreach ([
            Vendor::class,
            ProcurementItem::class,
            ProcurementUnit::class,
            ProcurementVariant::class,
            DepartureBatch::class,
        ] as $model) {
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
