<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\Role;
use App\Models\UserAssignment;
use App\Policies\ActivityPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\OfficePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserAssignmentPolicy;
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
        $this->app->scoped(ActiveOfficeContext::class);
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
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SpatieRole::class, RolePolicy::class);
        Gate::policy(UserAssignment::class, UserAssignmentPolicy::class);
    }
}
