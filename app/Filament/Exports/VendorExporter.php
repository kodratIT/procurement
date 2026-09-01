<?php

namespace App\Filament\Exports;

use App\Models\Vendor;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class VendorExporter extends Exporter
{
    protected static ?string $model = Vendor::class;

    public static function getColumns(): array
    {
        $canViewSensitiveData = Gate::allows('viewSensitiveData', Vendor::make());

        return [
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('contact_name')->visible($canViewSensitiveData),
            ExportColumn::make('phone')->visible($canViewSensitiveData),
            ExportColumn::make('email')->visible($canViewSensitiveData),
            ExportColumn::make('address')->visible($canViewSensitiveData),
            ExportColumn::make('is_active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your vendor export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
