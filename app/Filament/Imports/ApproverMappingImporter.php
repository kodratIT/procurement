<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\ApproverMapping;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class ApproverMappingImporter extends Importer
{
    protected static ?string $model = ApproverMapping::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('workflow_step_id')->label('Workflow Step ID')->numeric()->rules(['nullable', 'integer', 'exists:workflow_steps,id']),
            ImportColumn::make('resolver_type')->label('Resolver')->requiredMapping()->rules(['required', Rule::in(ApproverMapping::RESOLVER_TYPES)]),
            ImportColumn::make('role_id')->label('Role ID')->numeric()->rules(['nullable', 'integer', 'exists:roles,id']),
            ImportColumn::make('user_id')->label('User ID')->numeric()->rules(['nullable', 'integer', 'exists:users,id']),
            ImportColumn::make('office_id')->label('Office ID')->numeric()->rules(['nullable', 'integer', 'exists:offices,id']),
            ImportColumn::make('branch_id')->label('Branch ID')->numeric()->rules(['nullable', 'integer', 'exists:branches,id']),
            ImportColumn::make('department_id')->label('Department ID')->numeric()->rules(['nullable', 'integer', 'exists:departments,id']),
            ImportColumn::make('cost_center_id')->label('Cost Center ID')->numeric()->rules(['nullable', 'integer', 'exists:cost_centers,id']),
            ImportColumn::make('scope_source')->label('Scope Source')->rules(['required', Rule::in(ApproverMapping::SCOPE_SOURCES)]),
            ImportColumn::make('fallback_type')->label('Fallback')->rules(['required', Rule::in(ApproverMapping::FALLBACK_TYPES)]),
            ImportColumn::make('fallback_role_id')->label('Fallback Role ID')->numeric()->rules(['nullable', 'integer', 'exists:roles,id']),
            ImportColumn::make('fallback_user_id')->label('Fallback User ID')->numeric()->rules(['nullable', 'integer', 'exists:users,id']),
            ImportColumn::make('priority')->label('Priority')->numeric()->rules(['required', 'integer', 'min:0']),
            ImportColumn::make('allow_self_approval')->label('Self Approval')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('is_active')->label('Active')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('valid_from')->label('Valid From')->rules(['nullable', 'date']),
            ImportColumn::make('valid_until')->label('Valid Until')->rules(['nullable', 'date', 'after_or_equal:valid_from']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new ApproverMapping;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Impor mapping selesai: '.$import->successful_rows.' baris berhasil, '.$import->getFailedRowsCount().' gagal.';
    }
}
