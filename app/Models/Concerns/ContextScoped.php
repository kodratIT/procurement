<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ContextScoped
{
    public static function bootContextScoped(): void
    {
        static::addGlobalScope('access_context', function (Builder $query): void {
            if (Auth::check()) {
                app(MultiOfficeAuthorization::class)->scopeForCurrentContext($query);
            }
        });

        static::creating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            $officeId = app(AccessContextService::class)->id();
            if ($officeId !== null && $model->getAttribute('office_id') === null) {
                $model->setAttribute('office_id', $officeId);
            }

            app(MultiOfficeAuthorization::class)->authorizeMutation(
                Auth::user(),
                $model,
                app(MultiOfficeAuthorization::class)->mutationPermission($model),
            );
        });

        static::updating(function (Model $model): void {
            if (Auth::check()) {
                app(MultiOfficeAuthorization::class)->authorizeMutation(
                    Auth::user(),
                    $model,
                    app(MultiOfficeAuthorization::class)->mutationPermission($model),
                );
            }
        });

        static::deleting(function (Model $model): void {
            if (Auth::check()) {
                app(MultiOfficeAuthorization::class)->authorizeMutation(
                    Auth::user(),
                    $model,
                    ProcurementPermissions::DELETE,
                );
            }
        });
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->withoutGlobalScope('access_context')->where(
            $query->getModel()->qualifyColumn('office_id'),
            $officeId,
        );
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('branch_id'), $branchId);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('department_id'), $departmentId);
    }

    public function scopeForCurrentContext(Builder $query): Builder
    {
        return $query;
    }

    public function scopeAcrossContexts(Builder $query, ?string $permission = null): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereKey(0);
        }

        return app(MultiOfficeAuthorization::class)->scopeForUser(
            $query->withoutGlobalScope('access_context'),
            $user,
            $permission ?? ProcurementPermissions::VIEW,
        );
    }
}
