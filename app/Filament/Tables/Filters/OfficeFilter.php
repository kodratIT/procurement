<?php

namespace App\Filament\Tables\Filters;

use App\Models\Office;
use App\Services\ActiveOfficeContext;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restrict a scoped resource's table to offices the current user may access.
 *
 * By default the active office is applied (via the global scope). When the
 * user has the cross-office permission, this filter lets them narrow down to
 * any office they are assigned to; the scope is lifted only for those offices.
 */
class OfficeFilter extends SelectFilter
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kantor')
            ->attribute('office_id')
            ->options(fn (): array => app(ActiveOfficeContext::class)->availableOffices()
                ->mapWithKeys(fn (Office $office) => [$office->id => $office->name])
                ->all())
            ->placeholder('Semua kantor yang diizinkan')
            ->query(fn (Builder $query, array $data) => $this->apply($query, $data));
    }

    protected function apply(Builder $query, array $data): Builder
    {
        $context = app(ActiveOfficeContext::class);
        $officeId = $data['value'] ?? null;

        // Explicit selection: narrow to that office (only reachable for
        // offices the user is assigned to — options are already restricted).
        if ($officeId !== null) {
            return $query->where($query->getModel()->qualifyColumn('office_id'), $officeId);
        }

        // No selection: keep the global scope (active office only).
        return $query;
    }
}
