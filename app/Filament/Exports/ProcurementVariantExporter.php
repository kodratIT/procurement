<?php

namespace App\Filament\Exports;

use App\Models\ProcurementVariant;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ProcurementVariantExporter extends Exporter
{
    protected static ?string $model = ProcurementVariant::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('value'),
            ExportColumn::make('item.name')->label('Item'),
            ExportColumn::make('is_active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your variant export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
