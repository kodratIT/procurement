<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Widgets;

use App\Enums\WorkflowVersionStatus;
use App\Models\WorkflowVersion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class WorkflowVersionStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $total = WorkflowVersion::query()->count();
        $draft = WorkflowVersion::query()->where('status', WorkflowVersionStatus::Draft->value)->count();
        $active = WorkflowVersion::query()->where('status', WorkflowVersionStatus::Active->value)->count();
        $retired = WorkflowVersion::query()->where('status', WorkflowVersionStatus::Retired->value)->count();

        return [
            Stat::make('Total Versi', (string) $total)
                ->description('Semua versi')
                ->color('gray')
                ->icon('heroicon-o-queue-list'),
            Stat::make('Draft', (string) $draft)
                ->description('Belum aktif')
                ->color($draft > 0 ? 'gray' : 'success')
                ->icon('heroicon-o-pencil-square'),
            Stat::make('Aktif', (string) $active)
                ->description('Dipakai resolver')
                ->color($active > 0 ? 'success' : 'warning')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Pensiun', (string) $retired)
                ->description('Tidak dipakai lagi')
                ->color('warning')
                ->icon('heroicon-o-archive-box'),
        ];
    }
}
