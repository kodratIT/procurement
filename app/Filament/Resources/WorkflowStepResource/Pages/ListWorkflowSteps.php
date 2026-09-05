<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Pages;

use App\Filament\Resources\WorkflowStepResource;
use App\Filament\Resources\WorkflowStepResource\Widgets\WorkflowStepStats;
use App\Models\WorkflowStep;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWorkflowSteps extends ListRecords
{
    protected static string $resource = WorkflowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = WorkflowStep::query()
                        ->with(['workflowVersion.workflow', 'conditions', 'approverMappings'])
                        ->orderBy('workflow_version_id')
                        ->orderBy('sequence')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Workflow', 'Versi', 'Urutan', 'Nama', 'Tipe', 'Mode', 'Resolver', 'Wajib', 'SLA', 'Kondisi', 'Mappings', 'Created At',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->workflowVersion?->workflow?->name ?? '—',
                            $r->workflowVersion?->version_number ? 'v'.$r->workflowVersion->version_number : '—',
                            $r->sequence,
                            $r->name,
                            $r->step_type instanceof \BackedEnum ? $r->step_type->value : (string) $r->step_type,
                            $r->approval_mode instanceof \BackedEnum ? $r->approval_mode->value : (string) $r->approval_mode,
                            $r->resolver_type ?? '—',
                            $r->is_required ? 'Ya' : 'Tidak',
                            $r->sla_minutes ?? '—',
                            $r->conditions->count(),
                            $r->approverMappings->count(),
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'workflow-stages-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkflowStepStats::class,
        ];
    }
}
