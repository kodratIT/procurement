<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class ProcurementCategoryImporter extends Importer
{
    protected static ?string $model = ProcurementCategory::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')->label('Kode')->requiredMapping()->rules(['required', 'string', 'max:50', 'unique:procurement_categories,code']),
            ImportColumn::make('name')->label('Nama')->requiredMapping()->rules(['required', 'string', 'max:255']),
            ImportColumn::make('description')->label('Deskripsi')->rules(['nullable', 'string']),
            ImportColumn::make('type')->label('Tipe')->requiredMapping()->rules(['required', Rule::in(array_column(ProcurementCategoryType::cases(), 'value'))]),
            ImportColumn::make('requires_batch')->label('Wajib Batch')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_jamaah')->label('Wajib Jamaah')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_vendor')->label('Wajib Vendor')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_quotation')->label('Wajib Quotation')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_recommendation_reason')->label('Wajib Alasan Rekomendasi')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_recommendation_evidence')->label('Wajib Bukti Rekomendasi')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_receipt')->label('Wajib Receipt')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_invoice')->label('Wajib Invoice')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_po')->label('Wajib PO')->boolean()->rules(['nullable', 'boolean']),
            ImportColumn::make('workflow_reference')->label('Workflow')->rules(['nullable', 'string', 'max:100', 'exists:workflows,code']),
            ImportColumn::make('number_template')->label('Template Nomor')->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('is_active')->label('Aktif')->boolean()->rules(['nullable', 'boolean']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new ProcurementCategory;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Impor kategori selesai: '.$import->successful_rows.' baris berhasil, '.$import->getFailedRowsCount().' gagal.';
    }
}
