<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemStatusWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Versi PHP', PHP_VERSION)->description('Runtime aplikasi')->color('success'),
            Stat::make('Zona Waktu', config('app.timezone'))->description('Timezone aplikasi')->color('info'),
            Stat::make('Lingkungan', ucfirst((string) config('app.env')))->description('Environment saat ini')->color('warning'),
        ];
    }
}
