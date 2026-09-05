<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Pages;

use App\Filament\Imports\ProcurementCategoryImporter;
use App\Filament\Resources\ProcurementCategories\Widgets\ProcurementCategoryStats;
use App\Filament\Resources\ProcurementCategoryResource;
use App\Models\ProcurementCategory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListProcurementCategories extends ListRecords
{
    protected static string $resource = ProcurementCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = ProcurementCategory::query()
                        ->withCount(['items', 'fields', 'purchaseRequests'])
                        ->orderBy('name')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Kode', 'Nama', 'Deskripsi', 'Tipe',
                        'Wajib Batch', 'Wajib Jamaah', 'Wajib Vendor', 'Wajib Quotation',
                        'Wajib Alasan Rekomendasi', 'Wajib Bukti Rekomendasi',
                        'Wajib Receipt', 'Wajib Invoice', 'Wajib PO',
                        'Workflow', 'Template Nomor', 'Aktif', 'Dinonaktifkan', 'Items', 'Fields', 'PR', 'Dibuat',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->code,
                            $r->name,
                            $r->description ?? '—',
                            $r->type instanceof \BackedEnum ? $r->type->value : (string) $r->type,
                            $r->requires_batch ? 'Ya' : 'Tidak',
                            $r->requires_jamaah ? 'Ya' : 'Tidak',
                            $r->requires_vendor ? 'Ya' : 'Tidak',
                            $r->requires_quotation ? 'Ya' : 'Tidak',
                            $r->requires_recommendation_reason ? 'Ya' : 'Tidak',
                            $r->requires_recommendation_evidence ? 'Ya' : 'Tidak',
                            $r->requires_receipt ? 'Ya' : 'Tidak',
                            $r->requires_invoice ? 'Ya' : 'Tidak',
                            $r->requires_po ? 'Ya' : 'Tidak',
                            $r->workflow_reference ?? '—',
                            $r->number_template ?? '—',
                            $r->is_active ? 'Aktif' : 'Non-aktif',
                            $r->disabled_at?->toDateTimeString() ?? '—',
                            $r->items_count ?? $r->items()->count(),
                            $r->fields_count ?? $r->fields()->count(),
                            $r->purchase_requests_count ?? $r->purchaseRequests()->count(),
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'procurement-categories-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            ImportAction::make()
                ->importer(ProcurementCategoryImporter::class)
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray'),
            CreateAction::make()
                ->label('Buat Kategori')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProcurementCategoryStats::class,
        ];
    }
}
