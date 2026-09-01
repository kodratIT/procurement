<?php

namespace App\Filament\Exports;

use App\Models\ProcurementItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ProcurementItemExporter extends Exporter
{
    protected static ?string $model = ProcurementItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('SKU'),
            ExportColumn::make('name'),
            ExportColumn::make('category.name')->label('Kategori'),
            ExportColumn::make('unit.name')->label('Satuan'),
            ExportColumn::make('description'),
            ExportColumn::make('reference_price')->label('Harga Referensi'),
            ExportColumn::make('reference_currency')->label('Mata Uang'),
            ExportColumn::make('specifications')->label('Spesifikasi'),
            ExportColumn::make('is_active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your item export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
