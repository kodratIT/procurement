<?php

namespace App\Providers;

use App\Models\Activity;
use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use App\Services\ActiveOfficeContext;
use App\Services\Auth\HttpKeycloakOidcProvider;
use App\Services\Auth\KeycloakOidcProvider;
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
        $this->app->bind(KeycloakOidcProvider::class, HttpKeycloakOidcProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
    }
}
