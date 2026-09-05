<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Schemas;

use App\Models\ApproverMapping;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

final class ApproverMappingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Jenis Resolver')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextEntry::make('workflowStep.name')
                        ->label('Workflow Step')
                        ->state(fn (ApproverMapping $record): ?string => $record->workflowStep !== null ? $record->workflowStep->name.' ('.($record->workflowStep->workflowVersion?->workflow?->name ?? '—').')' : null)
                        ->placeholder('— Global (semua step)')
                        ->icon(Heroicon::OutlinedClipboardDocumentList),
                    TextEntry::make('workflowStep.workflowVersion.workflow.name')
                        ->label('Workflow')
                        ->placeholder('— Semua Workflow')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('workflowStep.workflowVersion.version_number')
                        ->label('Versi')
                        ->placeholder('—')
                        ->badge(),
                    TextEntry::make('resolver_type')
                        ->label('Resolver')
                        ->badge()
                        ->colors([
                            'info' => 'role_in_request_office',
                            'primary' => 'role_in_budget_owner_office',
                            'warning' => 'specific_user',
                            'success' => fn (string $state): bool => in_array($state, ['department_head', 'branch_head', 'cost_center_owner'], true),
                            'gray' => 'nominal_role',
                        ])
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'role_in_request_office' => 'Role di Kantor Pemohon',
                            'role_in_budget_owner_office' => 'Role di Kantor Budget',
                            'specific_user' => 'Orang Tertentu',
                            'department_head' => 'Kepala Departemen',
                            'branch_head' => 'Kepala Cabang',
                            'cost_center_owner' => 'Owner Cost Center',
                            'nominal_role' => 'Nominal Role',
                            default => str($state)->headline()->toString(),
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Siapa Approver-nya?')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    TextEntry::make('role.name')
                        ->label('Role / Jabatan')
                        ->placeholder('— Tidak pakai role')
                        ->badge()
                        ->color('primary')
                        ->icon(Heroicon::OutlinedUserGroup),
                    TextEntry::make('role.code')
                        ->label('Kode Role')
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('user.name')
                        ->label('Orang Tertentu')
                        ->placeholder('— Tidak pakai user')
                        ->badge()
                        ->color('warning')
                        ->icon(Heroicon::OutlinedUser),
                    TextEntry::make('user.email')
                        ->label('Email User')
                        ->placeholder('—')
                        ->copyable(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Batasan Organisasi (Scope)')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    TextEntry::make('office.name')
                        ->label('Kantor')
                        ->placeholder('— Semua Kantor')
                        ->badge()
                        ->color('info'),
                    TextEntry::make('branch.name')
                        ->label('Cabang')
                        ->placeholder('— Semua Cabang'),
                    TextEntry::make('department.name')
                        ->label('Departemen')
                        ->placeholder('— Semua Departemen'),
                    TextEntry::make('costCenter.name')
                        ->label('Cost Center')
                        ->placeholder('— Semua Cost Center'),
                    TextEntry::make('scope_source')
                        ->label('Sumber Kantor Acuan')
                        ->badge()
                        ->colors([
                            'info' => 'request_office',
                            'primary' => 'budget_owner_office',
                            'gray' => fn (string $state): bool => in_array($state, ['request_branch', 'request_department', 'request_cost_center', 'configured'], true),
                        ])
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'request_office' => 'Kantor Pemohon',
                            'budget_owner_office' => 'Kantor Budget',
                            'request_branch' => 'Cabang Pemohon',
                            'request_department' => 'Dept Pemohon',
                            'request_cost_center' => 'CC Pemohon',
                            'configured' => 'Konfigurasi',
                            default => $state,
                        }),
                    TextEntry::make('specificity')
                        ->label('Skor Spesifisitas')
                        ->state(fn (ApproverMapping $record): string => (string) collect(['office_id', 'branch_id', 'department_id', 'cost_center_id'])->filter(fn (string $f): bool => $record->{$f} !== null)->count().' / 4 + priority '.$record->priority)
                        ->badge()
                        ->color('gray'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Fallback & Prioritas')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    TextEntry::make('fallback_type')
                        ->label('Fallback')
                        ->badge()
                        ->colors([
                            'danger' => 'block',
                            'warning' => 'role',
                            'info' => 'user',
                        ])
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'block' => 'Block Submit',
                            'role' => 'Fallback Role',
                            'user' => 'Fallback User',
                            default => $state,
                        }),
                    TextEntry::make('fallbackRole.name')
                        ->label('Fallback Role')
                        ->placeholder('— Tidak ada')
                        ->badge()
                        ->visible(fn (ApproverMapping $record): bool => $record->fallback_type === 'role'),
                    TextEntry::make('fallbackUser.name')
                        ->label('Fallback User')
                        ->placeholder('— Tidak ada')
                        ->badge()
                        ->visible(fn (ApproverMapping $record): bool => $record->fallback_type === 'user'),
                    TextEntry::make('priority')
                        ->label('Prioritas')
                        ->badge()
                        ->color('gray'),
                    IconEntry::make('allow_self_approval')
                        ->label('Self Approval')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->falseIcon(Heroicon::OutlinedXCircle),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Masa Berlaku & Status')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    IconEntry::make('is_active')
                        ->label('Aktif')
                        ->boolean(),
                    TextEntry::make('valid_from')
                        ->label('Berlaku Dari')
                        ->date('d M Y')
                        ->placeholder('— Selamanya'),
                    TextEntry::make('valid_until')
                        ->label('Berlaku Sampai')
                        ->date('d M Y')
                        ->placeholder('∞ Tidak ada batas'),
                    TextEntry::make('isActiveAt')
                        ->label('Status Saat Ini')
                        ->state(fn (ApproverMapping $record): string => $record->isActiveAt() ? 'Aktif' : 'Tidak Aktif / Kadaluwarsa')
                        ->badge()
                        ->color(fn (ApproverMapping $record): string => $record->isActiveAt() ? 'success' : 'danger')
                        ->icon(fn (ApproverMapping $record): Heroicon|string => $record->isActiveAt() ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle),
                    TextEntry::make('settings')
                        ->label('Settings (JSON)')
                        ->placeholder('— Kosong')
                        ->state(fn (ApproverMapping $record): string => $record->settings !== null ? json_encode($record->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                        ->columnSpanFull()
                        ->copyable(),
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

            Section::make('Riwayat Perubahan')
                ->icon(Heroicon::OutlinedClock)
                ->description('5 aktivitas terakhir • log_name=workflow')
                ->schema([
                    TextEntry::make('activity_timeline')
                        ->hiddenLabel()
                        ->html()
                        ->state(function (ApproverMapping $record): string {
                            $activities = Activity::query()
                                ->where('subject_type', ApproverMapping::class)
                                ->where('subject_id', $record->getKey())
                                ->latest()
                                ->limit(5)
                                ->get();

                            if ($activities->isEmpty()) {
                                return '<div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada perubahan tercatat.</div>';
                            }

                            $labels = [
                                'workflow_step_id' => 'Step',
                                'resolver_type' => 'Resolver',
                                'role_id' => 'Role',
                                'user_id' => 'User',
                                'office_id' => 'Kantor',
                                'branch_id' => 'Cabang',
                                'department_id' => 'Departemen',
                                'cost_center_id' => 'Cost Center',
                                'scope_source' => 'Scope',
                                'fallback_type' => 'Fallback',
                                'fallback_role_id' => 'Fallback Role',
                                'fallback_user_id' => 'Fallback User',
                                'priority' => 'Prioritas',
                                'allow_self_approval' => 'Self Approval',
                                'valid_from' => 'Valid From',
                                'valid_until' => 'Valid Until',
                                'is_active' => 'Aktif',
                                'settings' => 'Settings',
                            ];

                            $formatValue = function (string $key, mixed $val): string {
                                if ($val === null || $val === '') {
                                    return '<span class="text-gray-400 dark:text-gray-500">—</span>';
                                }
                                if (is_bool($val)) {
                                    return $val ? 'Ya' : 'Tidak';
                                }
                                if (is_array($val)) {
                                    return e(json_encode($val, JSON_UNESCAPED_UNICODE));
                                }
                                $str = (string) $val;
                                if (str_contains($key, 'valid_') && strtotime($str)) {
                                    return e(Carbon::parse($str)->format('d M Y'));
                                }

                                return e($str);
                            };

                            $html = '<div class="space-y-4">';
                            foreach ($activities as $a) {
                                $causer = e($a->causer?->name ?? 'System');
                                $time = e($a->created_at?->format('d M Y H:i') ?? '—');
                                $event = strtolower((string) ($a->event ?? $a->description ?? 'updated'));
                                $desc = e($a->description ?? $event);

                                $badgeClass = match (true) {
                                    str_contains($event, 'created') => 'bg-green-100 text-green-800 ring-green-600/20 dark:bg-green-900 dark:text-green-200 dark:ring-green-700',
                                    str_contains($event, 'deleted') => 'bg-red-100 text-red-800 ring-red-600/20 dark:bg-red-900 dark:text-red-200 dark:ring-red-700',
                                    str_contains($event, 'updated') => 'bg-yellow-100 text-yellow-800 ring-yellow-600/20 dark:bg-yellow-900 dark:text-yellow-200 dark:ring-yellow-700',
                                    default => 'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600',
                                };

                                $props = $a->properties ?? collect();
                                $attrs = $props->get('attributes');
                                $old = $props->get('old');

                                $rows = '';
                                if (is_array($attrs) && $attrs !== []) {
                                    $attrs = collect($attrs)->except(['created_at', 'updated_at', 'id'])->all();
                                    $old = is_array($old) ? $old : [];

                                    foreach ($attrs as $k => $v) {
                                        if (! isset($labels[$k])) {
                                            continue;
                                        }
                                        $label = $labels[$k];
                                        $newVal = $formatValue($k, $v);
                                        $oldVal = array_key_exists($k, $old) ? $formatValue($k, $old[$k]) : null;

                                        if ($oldVal !== null && $oldVal !== $newVal) {
                                            $rows .= '<div class="flex justify-between gap-2 bg-amber-50 dark:bg-amber-900 rounded px-2 py-1 ring-1 ring-amber-200 dark:ring-amber-700"><span class="font-medium text-gray-700 dark:text-amber-200">'.e($label).'</span><span class="text-right"><span class="line-through text-gray-400 dark:text-gray-400">'.$oldVal.'</span> <span class="mx-1 text-gray-400 dark:text-gray-400">→</span> <span class="font-medium text-gray-900 dark:text-amber-100">'.$newVal.'</span></span></div>';
                                        } else {
                                            $rows .= '<div class="flex justify-between gap-2 bg-gray-50 dark:bg-gray-800 rounded px-2 py-1 ring-1 ring-gray-200 dark:ring-gray-700"><span class="font-medium text-gray-700 dark:text-gray-300">'.e($label).'</span><span class="font-medium text-gray-900 dark:text-gray-100">'.$newVal.'</span></div>';
                                        }
                                    }
                                }

                                if ($rows === '') {
                                    $rows = '<div class="text-xs text-gray-400 dark:text-gray-500 italic px-2">Tidak ada detail perubahan.</div>';
                                }

                                $html .= '<div class="relative pl-6 border-l-2 border-amber-300 dark:border-amber-600">'
                                    .'<div class="absolute -left-[5px] top-1 w-2.5 h-2.5 bg-amber-500 dark:bg-amber-500 rounded-full border-2 border-white dark:border-gray-800"></div>'
                                    .'<div class="flex flex-wrap items-center justify-between gap-2">'
                                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ring-1 ring-inset '.$badgeClass.'">'.e(ucfirst($event)).'</span>'
                                    .'<span class="text-xs text-gray-500 dark:text-gray-400">'.$time.'</span>'
                                    .'</div>'
                                    .'<div class="mt-1 text-xs text-gray-600 dark:text-gray-400">oleh <span class="font-medium text-gray-800 dark:text-gray-200">'.$causer.'</span> • '.$desc.'</div>'
                                    .'<div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5">'.$rows.'</div>'
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
}
