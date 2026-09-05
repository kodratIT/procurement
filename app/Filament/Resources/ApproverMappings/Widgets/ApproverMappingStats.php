<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Widgets;

use App\Models\ApproverMapping;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

final class ApproverMappingStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        $total = ApproverMapping::query()->count();
        $active = ApproverMapping::query()->activeAt(Carbon::today())->count();
        $inactive = $total - $active;

        $expired = ApproverMapping::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', Carbon::today())
            ->count();

        $blockWithoutFallback = ApproverMapping::query()
            ->where('fallback_type', 'block')
            ->where('is_active', true)
            ->activeAt(Carbon::today())
            ->count();

        $specificUser = ApproverMapping::query()
            ->where('resolver_type', 'specific_user')
            ->where('is_active', true)
            ->count();

        return [
            Stat::make('Total Mapping', (string) $total)
                ->description('Semua mapping')
                ->color('gray')
                ->icon('heroicon-o-rectangle-stack'),
            Stat::make('Aktif Hari Ini', (string) $active)
                ->description($inactive.' non-aktif/kadaluwarsa')
                ->color($active > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Kadaluwarsa', (string) $expired)
                ->description('valid_until lewat')
                ->color($expired > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Block Tanpa Fallback', (string) $blockWithoutFallback)
                ->description('Berisiko gagal submit')
                ->color($blockWithoutFallback > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-exclamation'),
            Stat::make('Specific User', (string) $specificUser)
                ->description('Hardcode orang')
                ->color('info')
                ->icon('heroicon-o-user'),
        ];
    }
}
