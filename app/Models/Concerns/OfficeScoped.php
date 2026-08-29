<?php

namespace App\Models\Concerns;

use App\Models\Office;
use App\Services\ActiveOfficeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

trait OfficeScoped
{
    public static function bootOfficeScoped(): void
    {
        static::addGlobalScope('office', function (Builder $builder): void {
            $officeId = app(ActiveOfficeContext::class)->id();
            // Fail closed when no authenticated user has an active office.
            $builder->where($builder->getModel()->qualifyColumn('office_id'), $officeId ?? 0);
        });

        static::creating(function ($model): void {
            if ($model->getAttribute('office_id') === null) {
                $officeId = app(ActiveOfficeContext::class)->id();
                if ($officeId !== null) {
                    $model->setAttribute('office_id', $officeId);
                }
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Local scope: query rows for one specific office. The office must be one
     * the current user is assigned to; otherwise access is denied (403).
     */
    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        abort_unless(
            auth()->user() !== null && app(ActiveOfficeContext::class)->hasAccess(Office::find($officeId)),
            403,
            'You are not assigned to this office.',
        );

        return $query->withoutGlobalScope('office')->where($query->getModel()->qualifyColumn('office_id'), $officeId);
    }

    /**
     * Escape hatch: lift the global scope entirely. Only users with the
     * cross-office permission may see data beyond their own offices.
     */
    public function scopeAcrossOffices(Builder $query): Builder
    {
        abort_unless(
            auth()->user() !== null && Gate::forUser(auth()->user())->allows('viewAllOffices'),
            403,
            'You are not allowed to access data across offices.',
        );

        return $query->withoutGlobalScope('office');
    }
}
