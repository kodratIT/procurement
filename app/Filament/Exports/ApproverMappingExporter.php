<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\ApproverMapping;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class ApproverMappingExporter extends Exporter
{
    protected static ?string $model = ApproverMapping::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('workflowStep.name')->label('Step'),
            ExportColumn::make('workflowStep.workflowVersion.workflow.name')->label('Workflow'),
            ExportColumn::make('resolver_type')->label('Resolver'),
            ExportColumn::make('role.name')->label('Role'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('office.name')->label('Kantor'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('department.name')->label('Departemen'),
            ExportColumn::make('costCenter.name')->label('Cost Center'),
            ExportColumn::make('scope_source')->label('Scope Source'),
            ExportColumn::make('fallback_type')->label('Fallback'),
            ExportColumn::make('fallbackRole.name')->label('Fallback Role'),
            ExportColumn::make('fallbackUser.name')->label('Fallback User'),
            ExportColumn::make('priority')->label('Priority'),
            ExportColumn::make('allow_self_approval')->label('Self Approval'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('valid_from')->label('Valid From'),
            ExportColumn::make('valid_until')->label('Valid Until'),
            ExportColumn::make('settings')->label('Settings'),
            ExportColumn::make('created_at')->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export approver mapping selesai: '.$export->successful_rows.' baris berhasil.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.$failedRowsCount.' gagal.';
        }

        return $body;
    }
}
