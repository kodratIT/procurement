<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Pages;

use App\Filament\Imports\ApproverMappingImporter;
use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Filament\Resources\ApproverMappings\Widgets\ApproverMappingStats;
use App\Models\ApproverMapping;
use App\Models\Office;
use App\Models\WorkflowStep;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListApproverMappings extends ListRecords
{
    protected static string $resource = ApproverMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulate')
                ->label('Simulasi Resolver')
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->modalHeading('Simulasi: Mapping mana yang kepilih?')
                ->modalDescription('Pilih konteks PR untuk lihat urutan mapping yang akan dipakai WorkflowResolver (priority*100 + spesifisitas).')
                ->modalWidth('3xl')
                ->form([
                    Select::make('office_id')
                        ->label('Kantor Pemohon')
                        ->options(fn (): array => Office::query()->where('is_active', true)->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('branch_id')
                        ->label('Cabang')
                        ->relationship('branch', 'name', fn (Builder $query, Get $get): Builder => $query->where('is_active', true)->when($get('office_id'), fn (Builder $q, $oid): Builder => $q->where('office_id', $oid)))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua / Kosong'),
                    Select::make('workflow_step_id')
                        ->label('Workflow Step (opsional)')
                        ->options(fn (): array => WorkflowStep::query()->with('workflowVersion.workflow')->orderBy('sequence')->get()->mapWithKeys(fn (WorkflowStep $s): array => [$s->getKey() => $s->name.' ('.($s->workflowVersion?->workflow?->name ?? '—').')'])->all())
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua step'),
                    Select::make('resolver_type')
                        ->label('Resolver (opsional)')
                        ->options([
                            'role_in_request_office' => 'Role di Kantor Pemohon',
                            'role_in_budget_owner_office' => 'Role di Kantor Budget',
                            'specific_user' => 'Orang Tertentu',
                            'department_head' => 'Kepala Dept',
                            'branch_head' => 'Kepala Cabang',
                            'cost_center_owner' => 'Owner CC',
                            'nominal_role' => 'Nominal Role',
                        ])
                        ->placeholder('— Semua resolver'),
                ])
                ->action(function (array $data): void {
                    $officeId = $data['office_id'];
                    $branchId = $data['branch_id'] ?? null;
                    $stepId = $data['workflow_step_id'] ?? null;
                    $resolver = $data['resolver_type'] ?? null;
                    $date = Carbon::today();

                    $query = ApproverMapping::query()
                        ->activeAt($date)
                        ->when($resolver, fn (Builder $q): Builder => $q->where('resolver_type', $resolver))
                        ->when($stepId, fn (Builder $q): Builder => $q->where(fn (Builder $qq): Builder => $qq->whereNull('workflow_step_id')->orWhere('workflow_step_id', $stepId)))
                        ->with(['workflowStep.workflowVersion.workflow', 'role', 'user', 'office', 'branch', 'department', 'costCenter']);

                    $all = $query->get()
                        ->filter(function (ApproverMapping $m) use ($officeId, $branchId): bool {
                            if ($m->office_id !== null && (int) $m->office_id !== (int) $officeId) {
                                return false;
                            }
                            if ($m->branch_id !== null && $branchId !== null && (int) $m->branch_id !== (int) $branchId) {
                                return false;
                            }
                            if ($m->branch_id !== null && $branchId === null) {
                                return false;
                            }

                            return true;
                        })
                        ->sortByDesc(fn (ApproverMapping $m): int => $m->priority * 100 + collect(['office_id', 'branch_id', 'department_id', 'cost_center_id'])->filter(fn (string $f): bool => $m->{$f} !== null)->count())
                        ->values();

                    if ($all->isEmpty()) {
                        Notification::make()
                            ->title('Tidak ada mapping cocok')
                            ->body('Tidak ada mapping aktif untuk konteks tersebut. PR akan Block saat submit.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $lines = $all->take(5)->map(function (ApproverMapping $m, int $idx): string {
                        $scope = implode(' › ', array_filter([$m->office?->name, $m->branch?->name, $m->department?->name, $m->costCenter?->name])) ?: '— Semua';
                        $approver = $m->user?->name ?? $m->role?->name ?? '—';
                        $step = $m->workflowStep !== null ? $m->workflowStep->name.' ('.($m->workflowStep->workflowVersion?->workflow?->name ?? '—').')' : '— Global';
                        $score = $m->priority * 100 + collect(['office_id', 'branch_id', 'department_id', 'cost_center_id'])->filter(fn (string $f): bool => $m->{$f} !== null)->count();

                        return sprintf('%d. [%s] %s → %s | %s | prio %d skor %d | %s', $idx + 1, $m->resolver_type, $step, $approver, $scope, $m->priority, $score, $m->is_active ? 'aktif' : 'non-aktif');
                    })->implode("\n");

                    $more = $all->count() > 5 ? "\n... dan ".($all->count() - 5).' lagi (total '.$all->count().')' : '';

                    Notification::make()
                        ->title('Hasil simulasi ('.$all->count().' mapping cocok, urut prioritas)')
                        ->body($lines.$more)
                        ->success()
                        ->duration(10000)
                        ->send();
                }),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = ApproverMapping::query()
                        ->with(['workflowStep.workflowVersion.workflow', 'role', 'user', 'office', 'branch', 'department', 'costCenter', 'fallbackRole', 'fallbackUser'])
                        ->orderBy('priority', 'desc')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Step', 'Workflow', 'Resolver', 'Role', 'User', 'Kantor', 'Cabang', 'Departemen', 'Cost Center', 'Scope Source', 'Fallback', 'Fallback Role', 'Fallback User', 'Priority', 'Self Approval', 'Active', 'Valid From', 'Valid Until', 'Created At',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->workflowStep?->name ?? 'Global',
                            $r->workflowStep?->workflowVersion?->workflow?->name ?? '—',
                            $r->resolver_type,
                            $r->role?->name ?? '—',
                            $r->user?->name ?? '—',
                            $r->office?->name ?? '—',
                            $r->branch?->name ?? '—',
                            $r->department?->name ?? '—',
                            $r->costCenter?->name ?? '—',
                            $r->scope_source,
                            $r->fallback_type,
                            $r->fallbackRole?->name ?? '—',
                            $r->fallbackUser?->name ?? '—',
                            $r->priority,
                            $r->allow_self_approval ? 'Ya' : 'Tidak',
                            $r->is_active ? 'Aktif' : 'Non-aktif',
                            $r->valid_from?->toDateString() ?? '—',
                            $r->valid_until?->toDateString() ?? '∞',
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'approver-mappings-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            ImportAction::make()
                ->importer(ApproverMappingImporter::class)
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray'),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApproverMappingStats::class,
        ];
    }
}
