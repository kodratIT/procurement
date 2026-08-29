<?php

namespace App\Filament\Exports;

use App\Models\ProcurementCategory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ProcurementCategoryExporter extends Exporter
{
    protected static ?string $model = ProcurementCategory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('type'),
            ExportColumn::make('description'),
            ExportColumn::make('requires_batch'),
            ExportColumn::make('requires_vendor'),
            ExportColumn::make('receiving'),
            ExportColumn::make('invoice'),
            ExportColumn::make('jamaah'),
            ExportColumn::make('is_active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your category export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
