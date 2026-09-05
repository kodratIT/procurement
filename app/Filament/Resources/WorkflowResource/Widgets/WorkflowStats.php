<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Widgets;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class WorkflowStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $total = Workflow::query()->count();
        $active = Workflow::query()->where('is_active', true)->count();
        $inactive = $total - $active;

        $withoutActiveVersion = Workflow::query()
            ->whereDoesntHave('versions', fn ($q) => $q->where('status', 'active'))
            ->count();

        $totalVersions = WorkflowVersion::query()->count();

        return [
            Stat::make('Total Workflow', (string) $total)
                ->description('Semua workflow')
                ->color('gray')
                ->icon('heroicon-o-arrows-right-left'),
            Stat::make('Aktif', (string) $active)
                ->description($inactive.' non-aktif')
                ->color($active > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Tanpa Versi Aktif', (string) $withoutActiveVersion)
                ->description('Belum bisa dipakai approval')
                ->color($withoutActiveVersion > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Total Versi', (string) $totalVersions)
                ->description('Semua versi workflow')
                ->color('info')
                ->icon('heroicon-o-queue-list'),
        ];
    }
}
