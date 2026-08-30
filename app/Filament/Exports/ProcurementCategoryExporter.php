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
            ExportColumn::make('description'),
            ExportColumn::make('type'),
            ExportColumn::make('requires_batch'),
            ExportColumn::make('requires_jamaah'),
            ExportColumn::make('requires_vendor'),
            ExportColumn::make('requires_quotation'),
            ExportColumn::make('requires_receipt'),
            ExportColumn::make('requires_invoice'),
            ExportColumn::make('requires_po'),
            ExportColumn::make('workflow_reference'),
            ExportColumn::make('number_template'),
            ExportColumn::make('is_active'),
            ExportColumn::make('disabled_at'),
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
