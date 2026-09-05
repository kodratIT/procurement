<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Schemas;

use App\Models\ApproverDelegation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

final class ApproverDelegationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Penugasan Delegasi')
                ->icon(Heroicon::OutlinedArrowPath)
                ->description('Siapa yang didelegasikan dan siapa penggantinya.')
                ->schema([
                    TextEntry::make('delegator.name')
                        ->label('Original Approver')
                        ->placeholder('— Tidak ada')
                        ->badge()
                        ->color('primary')
                        ->icon(Heroicon::OutlinedUser),
                    TextEntry::make('delegator.email')
                        ->label('Email Original')
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('delegate.name')
                        ->label('Delegate (Pengganti)')
                        ->placeholder('— Tidak ada')
                        ->badge()
                        ->color('info')
                        ->icon(Heroicon::OutlinedArrowPath),
                    TextEntry::make('delegate.email')
                        ->label('Email Delegate')
                        ->placeholder('—')
                        ->copyable(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Masa Berlaku & Status')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    TextEntry::make('valid_from')
                        ->label('Berlaku Dari')
                        ->date('d M Y')
                        ->placeholder('—'),
                    TextEntry::make('valid_until')
                        ->label('Berlaku Sampai')
                        ->date('d M Y')
                        ->placeholder('∞ Tidak ada batas'),
                    IconEntry::make('is_active')
                        ->label('Aktif')
                        ->boolean(),
                    TextEntry::make('isActiveAt')
                        ->label('Status Saat Ini')
                        ->state(fn (ApproverDelegation $record): string => $record->isActiveAt() ? 'Aktif' : 'Tidak Aktif / Kadaluwarsa')
                        ->badge()
                        ->color(fn (ApproverDelegation $record): string => $record->isActiveAt() ? 'success' : 'danger')
                        ->icon(fn (ApproverDelegation $record): Heroicon|string => $record->isActiveAt() ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle),
                    TextEntry::make('duration')
                        ->label('Durasi')
                        ->state(function (ApproverDelegation $record): string {
                            if ($record->valid_from === null || $record->valid_until === null) {
                                return '—';
                            }

                            $days = $record->valid_from->diffInDays($record->valid_until) + 1;

                            return $days.' hari';
                        })
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('remaining')
                        ->label('Sisa Waktu')
                        ->state(function (ApproverDelegation $record): string {
                            if ($record->valid_until === null) {
                                return '∞';
                            }
                            $today = Carbon::today();
                            if ($record->valid_until->lt($today)) {
                                return 'Kadaluwarsa '.abs($today->diffInDays($record->valid_until)).' hari lalu';
                            }

                            return $today->diffInDays($record->valid_until).' hari lagi';
                        })
                        ->badge()
                        ->color(fn (ApproverDelegation $record): string => $record->valid_until !== null && $record->valid_until->lt(Carbon::today()) ? 'danger' : 'success'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Alasan & Metadata')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    TextEntry::make('reason')
                        ->label('Alasan Delegasi')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Riwayat Perubahan')
                ->icon(Heroicon::OutlinedClock)
                ->description('5 aktivitas terakhir • log_name=workflow')
                ->schema([
                    TextEntry::make('activity_timeline')
                        ->hiddenLabel()
                        ->html()
                        ->state(function (ApproverDelegation $record): string {
                            $activities = Activity::query()
                                ->where('subject_type', ApproverDelegation::class)
                                ->where('subject_id', $record->getKey())
                                ->latest()
                                ->limit(5)
                                ->get();

                            if ($activities->isEmpty()) {
                                return '<div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada perubahan tercatat.</div>';
                            }

                            $labels = [
                                'delegator_id' => 'Original',
                                'delegate_id' => 'Delegate',
                                'valid_from' => 'Valid From',
                                'valid_until' => 'Valid Until',
                                'is_active' => 'Aktif',
                                'reason' => 'Alasan',
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
