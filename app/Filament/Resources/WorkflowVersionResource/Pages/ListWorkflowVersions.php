<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Pages;

use App\Filament\Resources\WorkflowVersionResource;
use App\Filament\Resources\WorkflowVersionResource\Widgets\WorkflowVersionStats;
use App\Models\WorkflowVersion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWorkflowVersions extends ListRecords
{
    protected static string $resource = WorkflowVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = WorkflowVersion::query()
                        ->with(['workflow', 'steps'])
                        ->orderBy('workflow_id')
                        ->orderBy('version_number', 'desc')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Workflow', 'Versi', 'Status', 'Tahap', 'Efektif Dari', 'Efektif Sampai', 'Dipakai', 'Created At',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->workflow?->name ?? '—',
                            'v'.$r->version_number,
                            $r->status instanceof \BackedEnum ? $r->status->value : (string) $r->status,
                            $r->steps->count(),
                            $r->effective_from?->toDateTimeString() ?? '—',
                            $r->effective_until?->toDateTimeString() ?? '∞',
                            $r->isUsed() ? 'Ya' : 'Tidak',
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'workflow-versions-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkflowVersionStats::class,
        ];
    }
}
