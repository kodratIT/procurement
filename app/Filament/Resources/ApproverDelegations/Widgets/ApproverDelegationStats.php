<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Widgets;

use App\Models\ApproverDelegation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

final class ApproverDelegationStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        $total = ApproverDelegation::query()->count();
        $active = ApproverDelegation::query()->activeAt(Carbon::today())->count();
        $inactive = $total - $active;

        $expired = ApproverDelegation::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', Carbon::today())
            ->count();

        $expiringSoon = ApproverDelegation::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', Carbon::today())
            ->whereDate('valid_until', '<=', Carbon::today()->addDays(7))
            ->count();

        $overlapping = ApproverDelegation::query()
            ->where('is_active', true)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('approver_delegations as ad2')
                    ->whereColumn('ad2.delegator_id', 'approver_delegations.delegator_id')
                    ->whereColumn('ad2.id', '!=', 'approver_delegations.id')
                    ->where('ad2.is_active', true)
                    ->whereRaw('ad2.valid_from <= approver_delegations.valid_until')
                    ->whereRaw('ad2.valid_until >= approver_delegations.valid_from');
            })
            ->count();

        return [
            Stat::make('Total Delegasi', (string) $total)
                ->description('Semua delegasi')
                ->color('gray')
                ->icon('heroicon-o-arrow-path'),
            Stat::make('Aktif Hari Ini', (string) $active)
                ->description($inactive.' non-aktif/kadaluwarsa')
                ->color($active > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Kadaluwarsa', (string) $expired)
                ->description('valid_until lewat')
                ->color($expired > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Akan Habis ≤7 Hari', (string) $expiringSoon)
                ->description('Perlu perpanjangan')
                ->color($expiringSoon > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
            Stat::make('Tumpang Tindih', (string) $overlapping)
                ->description('Delegator double booking')
                ->color($overlapping > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
