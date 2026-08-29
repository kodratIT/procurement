<?php

namespace App\Models\Concerns;

use App\Models\Office;
use App\Services\ActiveOfficeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->withoutGlobalScope('office')->where($query->getModel()->qualifyColumn('office_id'), $officeId);
    }

    public function scopeAcrossOffices(Builder $query): Builder
    {
        return $query->withoutGlobalScope('office');
    }
}
