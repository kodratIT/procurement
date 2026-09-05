<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Widgets;

use App\Models\WorkflowStep;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class WorkflowStepStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $total = WorkflowStep::query()->count();
        $required = WorkflowStep::query()->where('is_required', true)->count();
        $optional = $total - $required;
        $withConditions = WorkflowStep::query()->whereHas('conditions')->count();
        $withSla = WorkflowStep::query()->whereNotNull('sla_minutes')->count();

        return [
            Stat::make('Total Tahap', (string) $total)
                ->description($required.' wajib, '.$optional.' opsional')
                ->color('gray')
                ->icon('heroicon-o-list-bullet'),
            Stat::make('Dengan Kondisi', (string) $withConditions)
                ->description('Tahap conditional')
                ->color($withConditions > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-adjustments-horizontal'),
            Stat::make('Dengan SLA', (string) $withSla)
                ->description('Punya batas waktu')
                ->color($withSla > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock'),
            Stat::make('Wajib', (string) $required)
                ->description('Tahap required')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
