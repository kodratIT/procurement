<?php

namespace App\Filament\Exports;

use App\Models\ProcurementCategory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProcurementCategoryExporter extends Exporter
{
    protected static ?string $model = ProcurementCategory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('Kode'),
            ExportColumn::make('name')->label('Nama'),
            ExportColumn::make('description')->label('Deskripsi'),
            ExportColumn::make('type')->label('Tipe'),
            ExportColumn::make('requires_batch')->label('Wajib Batch'),
            ExportColumn::make('requires_jamaah')->label('Wajib Jamaah'),
            ExportColumn::make('requires_vendor')->label('Wajib Vendor'),
            ExportColumn::make('requires_quotation')->label('Wajib Quotation'),
            ExportColumn::make('requires_recommendation_reason')->label('Wajib Alasan Rekomendasi'),
            ExportColumn::make('requires_recommendation_evidence')->label('Wajib Bukti Rekomendasi'),
            ExportColumn::make('requires_receipt')->label('Wajib Receipt'),
            ExportColumn::make('requires_invoice')->label('Wajib Invoice'),
            ExportColumn::make('requires_po')->label('Wajib PO'),
            ExportColumn::make('workflow_reference')->label('Workflow'),
            ExportColumn::make('number_template')->label('Template Nomor'),
            ExportColumn::make('is_active')->label('Aktif'),
            ExportColumn::make('disabled_at')->label('Dinonaktifkan'),
            ExportColumn::make('created_at')->label('Dibuat'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export kategori selesai: '.$export->successful_rows.' baris berhasil.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.$failedRowsCount.' gagal.';
        }

        return $body;
    }
}
