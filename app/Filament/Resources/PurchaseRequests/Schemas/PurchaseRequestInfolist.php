<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Schemas;

use App\Enums\PurchaseRequestStatus;
use App\Models\ApprovalInstance;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\Workflow;
use App\Services\WorkflowStageService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;
use Webkul\ProgressStepper\Enums\ConnectorShape;
use Webkul\ProgressStepper\Enums\Size;
use Webkul\ProgressStepper\Enums\Theme;
use Webkul\ProgressStepper\Infolists\Components\ProgressStepper;

final class PurchaseRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan PR')
                ->icon(Heroicon::OutlinedHashtag)
                ->schema([
                    TextEntry::make('pr_number')
                        ->label('Nomor PR')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->placeholder('— DRAFT')
                        ->icon(Heroicon::OutlinedHashtag),
                    TextEntry::make('title')
                        ->label('Judul')
                        ->placeholder('— tanpa judul')
                        ->weight('medium')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->columnSpan(2),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->colors([
                            'gray' => PurchaseRequest::STATUS_DRAFT,
                            'info' => [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW, PurchaseRequest::STATUS_PENDING_APPROVAL],
                            'warning' => PurchaseRequest::STATUS_RETURNED,
                            'success' => [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED],
                            'danger' => [PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_CANCELLED],
                        ])
                        ->color(function (string $state, PurchaseRequest $record): string {
                            if (in_array($state, [PurchaseRequest::STATUS_DRAFT], true)) {
                                return 'gray';
                            }
                            if (in_array($state, [PurchaseRequest::STATUS_RETURNED], true)) {
                                return 'warning';
                            }
                            if (in_array($state, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED], true)) {
                                return 'success';
                            }
                            if (in_array($state, [PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_CANCELLED], true)) {
                                return 'danger';
                            }
                            // Dynamic workflow stage -> info (flexible for new workflows)
                            $stageService = app(WorkflowStageService::class);
                            if ($stageService->isDynamicStage($state, $record)) {
                                return 'info';
                            }

                            return 'info';
                        })
                        ->formatStateUsing(function (string $state, PurchaseRequest $record): string {
                            $stageService = app(WorkflowStageService::class);
                            $label = $stageService->labelFor($state, $record);
                            // Fallback to enum if not dynamic
                            if ($label !== $state) {
                                return $label;
                            }

                            return match ($state) {
                                PurchaseRequestStatus::Draft->value => 'Draft',
                                PurchaseRequestStatus::Submitted->value => 'Diajukan',
                                PurchaseRequestStatus::ProcurementReview->value => 'Review Pengadaan',
                                PurchaseRequestStatus::PendingApproval->value => 'Menunggu Persetujuan',
                                PurchaseRequestStatus::Approved->value => 'Disetujui',
                                PurchaseRequestStatus::Rejected->value => 'Ditolak',
                                PurchaseRequestStatus::Returned->value => 'Dikembalikan',
                                PurchaseRequestStatus::Completed->value => 'Selesai',
                                PurchaseRequestStatus::Cancelled->value => 'Dibatalkan',
                                default => $stageService->labelFor($state, $record),
                            };
                        }),
                    TextEntry::make('priority')
                        ->label('Prioritas')
                        ->badge()
                        ->colors([
                            'gray' => 'low',
                            'info' => 'normal',
                            'warning' => 'high',
                            'danger' => 'urgent',
                        ])
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'low' => 'Rendah',
                            'normal' => 'Normal',
                            'high' => 'Tinggi',
                            'urgent' => 'Mendesak',
                            default => $state ?? '-',
                        }),
                    TextEntry::make('total_amount')
                        ->label('Total')
                        ->money('IDR', locale: 'id')
                        ->weight('medium'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Konteks Organisasi & Kategori')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->description('Kategori menentukan workflow approval & template nomor (diambil dari DB).')
                ->schema([
                    TextEntry::make('category.name')
                        ->label('Kategori')
                        ->placeholder('—')
                        ->badge()
                        ->color('info')
                        ->icon(Heroicon::OutlinedTag),
                    TextEntry::make('category.workflow_reference')
                        ->label('Workflow')
                        ->placeholder('— default')
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedCog6Tooth),
                    TextEntry::make('category.type')
                        ->label('Tipe Kategori')
                        ->placeholder('—')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state),
                    TextEntry::make('office.name')
                        ->label('Kantor')
                        ->placeholder('—')
                        ->icon(Heroicon::OutlinedBuildingOffice2),
                    TextEntry::make('branch.name')
                        ->label('Cabang')
                        ->placeholder('— Semua')
                        ->icon(Heroicon::OutlinedMapPin),
                    TextEntry::make('department.name')
                        ->label('Departemen')
                        ->placeholder('—')
                        ->icon(Heroicon::OutlinedUserGroup),
                    TextEntry::make('costCenter.name')
                        ->label('Cost Center')
                        ->placeholder('—')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('umrahBatch.name')
                        ->label('Batch Umrah')
                        ->placeholder('— tidak pakai batch'),
                    TextEntry::make('requester.name')
                        ->label('Pengaju')
                        ->placeholder('—')
                        ->icon(Heroicon::OutlinedUser),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Detail Permintaan')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextEntry::make('reason')
                        ->label('Alasan')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('notes')
                        ->label('Catatan')
                        ->placeholder('— tidak ada catatan')
                        ->columnSpanFull(),
                    TextEntry::make('required_date')
                        ->label('Tanggal Kebutuhan')
                        ->date('d M Y')
                        ->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Item yang Diminta')
                ->icon(Heroicon::OutlinedCube)
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('procurementItem.name')
                                ->label('Item Katalog')
                                ->placeholder('— manual')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('item_name')
                                ->label('Nama Bebas')
                                ->placeholder('—')
                                ->visible(fn (mixed $state): bool => filled($state)),
                            TextEntry::make('quantity')
                                ->label('Qty')
                                ->numeric(2),
                            TextEntry::make('unit_price')
                                ->label('Harga Satuan')
                                ->money('IDR', locale: 'id'),
                            TextEntry::make('line_total')
                                ->label('Subtotal')
                                ->money('IDR', locale: 'id')
                                ->weight('medium'),
                            TextEntry::make('description')
                                ->label('Deskripsi')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                    TextEntry::make('items_count')
                        ->label('Jumlah Item')
                        ->state(fn (PurchaseRequest $record): string => (string) $record->items()->count().' item(s)')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('total_amount')
                        ->label('Total Keseluruhan')
                        ->money('IDR', locale: 'id')
                        ->weight('medium')
                        ->badge()
                        ->color('success'),
                ])
                ->columnSpanFull(),

            Section::make('Status & Riwayat — Progres Workflow')
                ->icon(Heroicon::OutlinedClock)
                ->description('Pantau setiap tahap persetujuan PR. Hijau berarti selesai, biru sedang diproses, dan abu-abu belum dimulai. Klik salah satu tahap untuk melihat riwayatnya.')
                ->schema([
                    ProgressStepper::make('workflow_progress')
                        ->label('Progres Workflow')
                        ->options(fn (PurchaseRequest $record): array => self::workflowOptions($record))
                        ->state(fn (PurchaseRequest $record) => self::currentWorkflowStep($record))
                        ->markCompletedUpToCurrent()
                        ->errorStates(fn (PurchaseRequest $record) => in_array($record->status, [PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_RETURNED, PurchaseRequest::STATUS_CANCELLED], true) ? [self::currentWorkflowStep($record)] : [])
                        ->completedColor('success')
                        ->currentColor(fn (PurchaseRequest $record): string => self::workflowIsComplete($record) ? 'success' : 'primary')
                        ->upcomingColor('gray')
                        ->errorColor('danger')
                        ->size(Size::Large)
                        ->theme(Theme::Outlined)
                        ->connectorShape(ConnectorShape::Chevron)
                        ->stepTooltip(fn (PurchaseRequest $record): array => self::stepTooltips($record))
                        ->extraAttributes(fn (PurchaseRequest $record): array => [
                            'class' => 'w-full ps-animated'.(self::workflowIsComplete($record) ? ' ps-workflow-complete' : ''),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Riwayat Perubahan')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    TextEntry::make('activity_timeline')
                        ->hiddenLabel()
                        ->html()
                        ->state(function (PurchaseRequest $record): string {
                            $entries = self::timelineEntries($record);

                            if ($entries === []) {
                                return '<div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada perubahan tercatat.</div>';
                            }

                            $html = '<div class="pr-history-timeline">';
                            $total = count($entries);
                            foreach ($entries as $idx => $entry) {
                                $isLast = $idx === $total - 1;
                                $isCurrent = $isLast && ! $entry['is_created'] && ! $entry['is_terminal'];
                                $statusClass = $isCurrent ? 'is-current' : 'is-completed';
                                $colorClass = 'is-'.$entry['color'];
                                $marker = match (true) {
                                    $entry['color'] === 'danger' => '<svg class="pr-history-marker-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>',
                                    $entry['color'] === 'warning' => '<span class="pr-history-marker-symbol">!</span>',
                                    $isCurrent => '<span class="pr-history-marker-dot"></span>',
                                    default => '<svg class="pr-history-marker-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                                };
                                $connector = $isLast ? '' : '<span class="pr-history-connector"></span>';
                                $description = e($entry['description']);
                                $meta = 'oleh '.e($entry['causer']).' · '.e($entry['time']);
                                $html .= '<div class="pr-history-item '.$statusClass.' '.$colorClass.'">'
                                    .'<div class="pr-history-marker-column">'
                                    .'<span class="pr-history-marker">'.$marker.'</span>'
                                    .$connector
                                    .'</div>'
                                    .'<div class="pr-history-content">'
                                    .'<div class="pr-history-title">'.e($entry['event']).'</div>'
                                    .($description !== '' ? '<div class="pr-history-description">'.$description.'</div>' : '')
                                    .'<div class="pr-history-meta">'.$meta.'</div>'
                                    .'</div>'
                                    .'</div>';
                            }
                            $html .= '</div>';

                            return $html;
                        })
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Merge lifecycle history and non-lifecycle audit entries into one ordered timeline.
     *
     * @return list<array{id:string,event:string,event_raw:string,stage_key:string,time:string,description:string,causer:string,status:string,color:string,is_created:bool,is_terminal:bool,is_completed:bool,sort_at:string,sort_priority:int,sort_id:int}>
     */
    public static function timelineEntries(PurchaseRequest $record): array
    {
        $statusHistories = $record->statusHistories()
            ->with('actor')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(fn (PurchaseRequestStatusHistory $history): bool => strtolower((string) $history->event) === 'correction_saved');

        $historyEntries = $statusHistories->map(function (PurchaseRequestStatusHistory $history) use ($record): array {
            $status = strtolower((string) $history->to_status);
            $event = strtolower((string) $history->event);
            $color = self::timelineColor($event, $status);

            return [
                'id' => 'history-'.$history->getKey(),
                'event' => self::timelineEventLabel($event, $status),
                'event_raw' => $event.' '.$status.' '.strtolower((string) $history->decision),
                'stage_key' => self::timelineStageKey($history),
                'time' => $history->created_at?->format('d M Y H:i') ?? '—',
                'description' => self::timelineHistoryDescription($record, $history),
                'causer' => $history->actor?->name ?? 'Sistem',
                'status' => $status,
                'color' => $color,
                'is_created' => false,
                'is_terminal' => self::isTerminalStatus($status),
                'is_completed' => false,
                'sort_at' => $history->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sort_priority' => 1,
                'sort_id' => (int) $history->getKey(),
            ];
        });

        $lifecycleEvents = [
            'submitted',
            'resubmitted',
            'forwarded',
            'approval_handoff',
            'approval_decision',
            'returned',
            'approved',
            'rejected',
            'correction_saved',
        ];

        $activityEntries = Activity::query()
            ->where('subject_type', PurchaseRequest::class)
            ->where('subject_id', $record->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(function (Activity $activity) use ($lifecycleEvents): bool {
                $event = strtolower((string) ($activity->event ?? ''));
                $afterStatus = data_get($activity->properties, 'after.status');

                return in_array($event, $lifecycleEvents, true)
                    || str_starts_with($event, 'review_')
                    || filled($afterStatus);
            })
            ->map(function (Activity $activity): array {
                $event = strtolower((string) ($activity->event ?? $activity->description ?? 'updated'));
                $description = trim((string) ($activity->description ?? ''));

                return [
                    'id' => 'activity-'.$activity->getKey(),
                    'event' => self::timelineEventLabel($event, ''),
                    'event_raw' => $event,
                    'stage_key' => str_contains($event, 'created') ? 'pengajuan' : '',
                    'time' => $activity->created_at?->format('d M Y H:i') ?? '—',
                    'description' => self::timelineActivityDescription($event, $description),
                    'causer' => $activity->causer?->name ?? 'Sistem',
                    'status' => '',
                    'color' => self::timelineColor($event, ''),
                    'is_created' => str_contains($event, 'created'),
                    'is_terminal' => false,
                    'is_completed' => false,
                    'sort_at' => $activity->created_at?->format('Y-m-d H:i:s.u') ?? '',
                    'sort_priority' => str_contains($event, 'created') ? 0 : 2,
                    'sort_id' => (int) $activity->getKey(),
                ];
            });

        $currentStage = self::currentWorkflowStep($record);
        $stageKeys = array_keys(self::workflowOptions($record));
        $currentIndex = array_search($currentStage, $stageKeys, true);
        $workflowComplete = self::workflowIsComplete($record);

        return $historyEntries
            ->concat($activityEntries)
            ->sort(function (array $left, array $right): int {
                $timestampComparison = strcmp($left['sort_at'], $right['sort_at']);

                if ($timestampComparison !== 0) {
                    return $timestampComparison;
                }

                $priorityComparison = $left['sort_priority'] <=> $right['sort_priority'];

                return $priorityComparison !== 0
                    ? $priorityComparison
                    : $left['sort_id'] <=> $right['sort_id'];
            })
            ->values()
            ->map(function (array $entry) use ($stageKeys, $currentIndex, $workflowComplete): array {
                $stageIndex = $entry['stage_key'] !== '' ? array_search($entry['stage_key'], $stageKeys, true) : false;
                $entry['is_completed'] = $entry['is_completed']
                    || $workflowComplete
                    || ($stageIndex !== false && $currentIndex !== false && $stageIndex < $currentIndex);

                return $entry;
            })
            ->map(fn (array $entry): array => array_diff_key($entry, ['sort_at' => true, 'sort_priority' => true, 'sort_id' => true]))
            ->all();
    }

    private static function timelineStageKey(PurchaseRequestStatusHistory $history): string
    {
        $status = strtolower((string) $history->to_status);

        // A correction belongs to the stage that sent the PR back, not to a
        // separate standalone stage. This keeps the correction note visible
        // in that stage's history modal when the request is resubmitted.
        if ($status === PurchaseRequest::STATUS_RETURNED) {
            $fromStatus = strtolower((string) $history->from_status);

            return $fromStatus === PurchaseRequest::STATUS_SUBMITTED
                ? 'pengajuan'
                : str($fromStatus)->slug('_')->toString();
        }

        if ($status === PurchaseRequest::STATUS_SUBMITTED) {
            return 'pengajuan';
        }

        if (in_array($status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED], true)) {
            return str((string) $history->from_status)->slug('_')->toString();
        }

        return str($status)->slug('_')->toString();
    }

    private static function timelineColor(string $event, string $status): string
    {
        $value = strtolower($event.' '.$status);

        return match (true) {
            str_contains($value, 'reject') => 'danger',
            str_contains($value, 'return'), str_contains($value, 'correction') => 'warning',
            str_contains($value, 'approv'), $status === PurchaseRequest::STATUS_COMPLETED => 'success',
            default => 'primary',
        };
    }

    private static function timelineEventLabel(string $event, string $status): string
    {
        $mapped = match ($status) {
            PurchaseRequest::STATUS_SUBMITTED => 'Diajukan',
            PurchaseRequest::STATUS_PROCUREMENT_REVIEW => 'Review Purchasing',
            PurchaseRequest::STATUS_PENDING_APPROVAL => 'Menunggu persetujuan',
            PurchaseRequest::STATUS_APPROVED => 'PR selesai',
            PurchaseRequest::STATUS_REJECTED => 'Ditolak',
            PurchaseRequest::STATUS_RETURNED => 'Perlu perbaikan',
            PurchaseRequest::STATUS_COMPLETED => 'PR selesai',
            PurchaseRequest::STATUS_CANCELLED => 'Dibatalkan',
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        // Dynamic workflow stage: humanize step_key
        if ($status !== '' && preg_match('/^[a-z0-9_]+$/', $status)) {
            return app(WorkflowStageService::class)->labelFor($status);
        }

        return str_contains($event, 'created') ? 'PR dibuat' : ucfirst(str_replace('_', ' ', $event));
    }

    private static function timelineHistoryDescription(PurchaseRequest $record, PurchaseRequestStatusHistory $history): string
    {
        $status = strtolower((string) $history->to_status);
        $note = trim((string) $history->note);

        if (in_array($status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED], true)) {
            $stageKey = str((string) $history->from_status)->slug('_')->toString();
            $stageLabel = $stageKey !== ''
                ? app(WorkflowStageService::class)->labelFor($stageKey, $record)
                : 'tahap akhir';
            $approver = $history->actor?->name ?? 'pihak yang berwenang';

            return "Disetujui {$approver} pada tahap {$stageLabel}. Seluruh tahap persetujuan selesai dan PR {$record->pr_number} telah disetujui.";
        }

        if ($status === PurchaseRequest::STATUS_SUBMITTED) {
            return "PR {$record->pr_number} telah diajukan dan menunggu proses persetujuan.";
        }

        if ($status === PurchaseRequest::STATUS_RETURNED) {
            return $note !== '' ? 'Catatan perbaikan: '.$note : 'PR dikembalikan kepada pengaju untuk diperbaiki.';
        }

        if ($status === PurchaseRequest::STATUS_REJECTED) {
            return $note !== '' ? 'Alasan penolakan: '.$note : 'PR ditolak dan proses persetujuan dihentikan.';
        }

        if ($status === PurchaseRequest::STATUS_CANCELLED) {
            return $note !== '' ? 'Alasan pembatalan: '.$note : 'PR dibatalkan sebelum proses persetujuan selesai.';
        }

        if ($note !== '' && ! str_contains(strtolower($note), 'workflow')) {
            return $note;
        }

        $stage = app(WorkflowStageService::class)->labelFor($status, $record);

        return "PR masuk ke tahap {$stage} dan menunggu tindakan pihak yang ditugaskan.";
    }

    private static function timelineActivityDescription(string $event, string $description): string
    {
        if (str_contains($event, 'created')) {
            return 'Dokumen PR dibuat dan siap dilengkapi.';
        }

        if ($description === '' || strcasecmp($description, $event) === 0) {
            return '';
        }

        return str_contains(strtolower($description), 'purchase request')
            ? 'Data PR diperbarui.'
            : $description;
    }

    private static function isTerminalStatus(string $status): bool
    {
        return in_array($status, [
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_COMPLETED,
            PurchaseRequest::STATUS_REJECTED,
            PurchaseRequest::STATUS_RETURNED,
            PurchaseRequest::STATUS_CANCELLED,
        ], true);
    }

    /** @return array<string,string> */
    public static function workflowOptions(PurchaseRequest $record): array
    {
        $approval = self::latestApprovalInstance($record);
        if ($approval instanceof ApprovalInstance) {
            $approval->loadMissing('workflowVersion.steps');
            $snapshotOptions = [];

            foreach ($approval->steps()->with('workflowStep')->orderBy('step_order')->orderBy('id')->get() as $step) {
                $key = str((string) ($step->step_key ?: $step->label))->slug('_')->toString();
                if ($key !== '' && ! isset($snapshotOptions[$key])) {
                    $currentWorkflowStep = $step->workflowStep
                        ?? $approval->workflowVersion?->steps->firstWhere('sequence', $step->step_order);
                    $snapshotOptions[$key] = $currentWorkflowStep?->name ?? $step->label;
                }
            }

            if ($snapshotOptions !== []) {
                unset($snapshotOptions['pengajuan']);

                return ['pengajuan' => 'Pengajuan'] + $snapshotOptions;
            }
        }

        $workflow = null;
        $workflowVersion = null;
        $steps = collect();

        $category = $record->relationLoaded('category') ? $record->category : $record->category()->first();
        if ($category && $category->workflow_reference) {
            $workflow = Workflow::query()->where('code', $category->workflow_reference)->first();
            if ($workflow) {
                $workflowVersion = $workflow->versions()->where('status', 'active')->latest('version_number')->first()
                    ?? $workflow->versions()->latest('version_number')->first();
                if ($workflowVersion) {
                    $steps = $workflowVersion->steps()->orderBy('sequence')->get();
                }
            }
        }

        $options = [];
        foreach ($steps as $ws) {
            $key = (string) ($ws->name ?? $ws->label ?? 'step_'.($ws->sequence ?? ''));
            $key = str($key)->slug('_')->toString();
            $options[$key] = $ws->name ?? $ws->label ?? 'Step';
        }

        if ($options === []) {
            $options = [
                'review_pengadaan' => 'Review Pengadaan',
                'persetujuan_keuangan' => 'Persetujuan Keuangan',
            ];
        }

        unset($options['pengajuan']);

        return ['pengajuan' => 'Pengajuan'] + $options;
    }

    private static function currentWorkflowStep(PurchaseRequest $record): string
    {
        $options = self::workflowOptions($record);
        $keys = array_keys($options);

        if ($keys === []) {
            return '';
        }

        $approval = self::latestApprovalInstance($record);

        if ($approval) {
            $steps = $approval->steps()->orderBy('step_order')->get();
            if ($steps->isNotEmpty()) {
                $pending = $steps->firstWhere(fn ($s) => in_array($s->status, ['pending', 'in_progress'], true));
                if ($pending) {
                    $label = (string) $pending->label;
                    $key = str((string) ($pending->step_key ?: $label))->slug('_')->toString();
                    if (isset($options[$key])) {
                        return $key;
                    }

                    foreach ($options as $k => $v) {
                        if (str_contains(strtolower($v), strtolower($label))) {
                            return $k;
                        }
                    }
                }
                $last = $steps->last();
                if ($last && in_array($last->status, ['approved', 'completed'], true)) {
                    return (string) end($keys);
                }
            }
        }

        $statusKey = str((string) $record->status)->slug('_')->toString();
        if (isset($options[$statusKey])) {
            return $statusKey;
        }

        return match ($record->status) {
            PurchaseRequest::STATUS_DRAFT => $keys[0],
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_PROCUREMENT_REVIEW,
            PurchaseRequest::STATUS_PENDING_APPROVAL => $keys[1] ?? $keys[0],
            PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED => end($keys),
            PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_RETURNED, PurchaseRequest::STATUS_CANCELLED => $keys[0],
            default => $keys[0],
        };
    }

    public static function workflowIsComplete(PurchaseRequest $record): bool
    {
        return in_array($record->status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED], true);
    }

    private static function latestApprovalInstance(PurchaseRequest $record): ?ApprovalInstance
    {
        if ($record->relationLoaded('approvalInstances')) {
            return $record->approvalInstances->sortByDesc('created_at')->first();
        }

        return ApprovalInstance::query()
            ->where('purchase_request_id', $record->getKey())
            ->latest('id')
            ->first();
    }

    /** @return array<string,string> */
    private static function stepDescriptions(PurchaseRequest $record): array
    {
        $category = $record->relationLoaded('category') ? $record->category : $record->category()->first();
        $workflowName = $category?->workflow_reference ?? 'default';

        return [
            'pengajuan' => 'Dibuat oleh '.($record->requester?->name ?? 'pemohon'),
            'acc_kepala_divisi' => 'Workflow: '.$workflowName,
            'acc_manager_sales' => 'Workflow: '.$workflowName,
            'review_purchasing' => 'Workflow: '.$workflowName,
            'acc_keuangan' => 'Workflow: '.$workflowName,
        ];
    }

    /** @return array<string,string> */
    private static function stepTooltips(PurchaseRequest $record): array
    {
        return [
            'pengajuan' => 'Tahap awal pengajuan PR',
            'acc_kepala_divisi' => 'Persetujuan Kepala Divisi',
            'acc_manager_sales' => 'Persetujuan Manager Sales',
            'review_purchasing' => 'Review oleh Purchasing',
            'acc_keuangan' => 'Persetujuan akhir Keuangan',
        ];
    }

    private static function nextStepLabel(PurchaseRequest $record): string
    {
        $options = self::workflowOptions($record);
        $current = self::currentWorkflowStep($record);
        $keys = array_keys($options);
        $idx = array_search($current, $keys, true);
        if ($idx === false) {
            return '—';
        }
        $nextIdx = $idx + 1;
        if ($record->status === PurchaseRequest::STATUS_DRAFT) {
            return $options[$keys[0]] ?? 'Pengajuan';
        }
        if (in_array($record->status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED], true)) {
            return 'Selesai';
        }

        return $options[$keys[$nextIdx] ?? $keys[$idx]] ?? '—';
    }

    private static function progressFraction(PurchaseRequest $record): string
    {
        $options = self::workflowOptions($record);
        $current = self::currentWorkflowStep($record);
        $keys = array_keys($options);
        $idx = array_search($current, $keys, true);

        return ($idx === false ? 1 : $idx + 1).'/'.count($keys);
    }
}
