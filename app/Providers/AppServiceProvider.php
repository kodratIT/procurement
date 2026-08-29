<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Office;
use App\Policies\ActivityPolicy;
use App\Policies\OfficePolicy;
use App\Policies\RolePolicy;
use App\Services\ActiveOfficeContext;
use App\Support\ProcurementPermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Cross-office visibility: only users who manage users may lift the
        // office scope and see data beyond their own assignments.
        Gate::define('viewAllOffices', fn ($user) => $user->can(ProcurementPermissions::MANAGE_USERS));
    }
}
