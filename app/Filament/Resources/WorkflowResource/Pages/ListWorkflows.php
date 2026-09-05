<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Pages;

use App\Filament\Resources\WorkflowResource;
use App\Filament\Resources\WorkflowResource\Widgets\WorkflowStats;
use App\Models\Workflow;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = Workflow::query()
                        ->withCount(['versions', 'bindings'])
                        ->orderBy('code')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Kode', 'Nama', 'Deskripsi', 'Versi', 'Versi Aktif', 'Aktif', 'Created At',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->code,
                            $r->name,
                            $r->description ?? '—',
                            $r->versions_count,
                            $r->activeVersion()?->version_number ? 'v'.$r->activeVersion()->version_number : '—',
                            $r->is_active ? 'Aktif' : 'Non-aktif',
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'workflows-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkflowStats::class,
        ];
    }
}
