<?php

namespace App\Models\Concerns;

use App\Models\Office;
use App\Models\User;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait OfficeScoped
{
    public static function bootOfficeScoped(): void
    {
        static::addGlobalScope('office', function (Builder $builder): void {
            app(MultiOfficeAuthorization::class)->scopeForCurrentContext($builder);
        });

        static::creating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            $context = app(AccessContextService::class);
            $officeId = $context->id();

            if ($officeId === null) {
                throw new AuthorizationException('An active office context is required.');
            }

            if ($model->getAttribute('office_id') === null) {
                $model->setAttribute('office_id', $officeId);
            }

            app(MultiOfficeAuthorization::class)->authorizeCreate(
                Auth::user(),
                $model,
                ProcurementPermissions::CREATE,
            );
        });

        static::updating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            if ($model->isDirty('office_id')
                && (int) $model->getAttribute('office_id') !== (int) $model->getOriginal('office_id')) {
                throw new AuthorizationException('The record office cannot be changed after creation.');
            }

            app(MultiOfficeAuthorization::class)->authorizeMutation(
                Auth::user(),
                $model,
                ProcurementPermissions::UPDATE,
            );
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

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->withoutGlobalScope('office')->where($query->getModel()->qualifyColumn('office_id'), $officeId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('branch_id'), $branchId);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('department_id'), $departmentId);
    }

    public function scopeAcrossOffices(Builder $query, ?string $permission = null): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->withoutGlobalScope('office');
        }

        if (! $user->assignments()->currentlyActive()->exists()) {
            return $query->withoutGlobalScope('office');
        }

        return app(MultiOfficeAuthorization::class)->scopeForUser(
            $query->withoutGlobalScope('office'),
            $user,
            $permission ?? ProcurementPermissions::VIEW,
        );
    }
}
