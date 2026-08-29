<?php

namespace App\Filament\Exports;

use App\Models\DepartureBatch;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class DepartureBatchExporter extends Exporter
{
    protected static ?string $model = DepartureBatch::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('office.name')->label('Kantor Pemilik'),
            ExportColumn::make('departure_date'),
            ExportColumn::make('return_date'),
            ExportColumn::make('capacity'),
            ExportColumn::make('pax_count'),
            ExportColumn::make('status'),
            ExportColumn::make('is_active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your departure batch export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
