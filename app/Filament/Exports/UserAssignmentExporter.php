<?php

namespace App\Filament\Exports;

use App\Models\UserAssignment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class UserAssignmentExporter extends Exporter
{
    protected static ?string $model = UserAssignment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('office.name')->label('Kantor'),
            ExportColumn::make('assignedRole.name')->label('Role'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('department.name')->label('Departemen'),
            ExportColumn::make('costCenter.name')->label('Cost center'),
            ExportColumn::make('is_primary')->label('Primary'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('valid_from'),
            ExportColumn::make('valid_until'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user assignment export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
