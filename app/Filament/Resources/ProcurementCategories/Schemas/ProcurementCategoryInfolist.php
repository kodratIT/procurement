<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Schemas;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use App\Models\Workflow;
use App\Support\ProcurementCategoryConfiguration;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

final class ProcurementCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Dasar')
                ->icon(Heroicon::OutlinedTag)
                ->schema([
                    TextEntry::make('code')
                        ->label('Kode')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->icon(Heroicon::OutlinedHashtag),
                    TextEntry::make('name')
                        ->label('Nama Kategori')
                        ->weight('medium')
                        ->icon(Heroicon::OutlinedTag),
                    TextEntry::make('type')
                        ->label('Tipe')
                        ->badge()
                        ->colors([
                            'info' => ProcurementCategoryType::Goods->value,
                            'success' => ProcurementCategoryType::Service->value,
                            'warning' => ProcurementCategoryType::GoodsAndServices->value,
                        ])
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof ProcurementCategoryType
                            ? $state->label()
                            : (ProcurementCategoryType::tryFrom((string) $state)?->label() ?? (string) $state)),
                    TextEntry::make('description')
                        ->label('Deskripsi')
                        ->placeholder('— Tidak ada deskripsi')
                        ->columnSpanFull()
                        ->icon(Heroicon::OutlinedDocumentText),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Persyaratan Proses')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->description('Centang = wajib diisi saat PR untuk kategori ini.')
                ->schema(
                    collect(ProcurementCategoryConfiguration::flagLabels())
                        ->map(fn (string $label, string $field): IconEntry => IconEntry::make($field)
                            ->label(str_replace('Wajib ', '', $label))
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedXCircle)
                            ->trueColor('success')
                            ->falseColor('gray'))
                        ->values()
                        ->all()
                )
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Workflow & Penomoran')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->description('Data diambil langsung dari database workflows & template existing.')
                ->schema([
                    TextEntry::make('workflow_reference')
                        ->label('Referensi Workflow')
                        ->placeholder('— default (standard-procurement)')
                        ->badge()
                        ->color(fn (?string $state): string => $state ? 'info' : 'gray')
                        ->copyable()
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->state(function (ProcurementCategory $record): ?string {
                            if ($record->workflow_reference === null || $record->workflow_reference === '') {
                                return null;
                            }

                            $name = Workflow::query()->where('code', $record->workflow_reference)->value('name');

                            return $name ? $record->workflow_reference.' — '.$name : $record->workflow_reference;
                        })
                        ->helperText(function (ProcurementCategory $record): ?string {
                            if ($record->workflow_reference === null || $record->workflow_reference === '') {
                                return 'Akan fallback ke workflow standard-procurement.';
                            }

                            $workflow = Workflow::query()->where('code', $record->workflow_reference)->first();

                            return $workflow ? ($workflow->is_active ? 'Workflow aktif: '.$workflow->name : 'Workflow non-aktif: '.$workflow->name) : 'Kode workflow tidak ditemukan di DB.';
                        }),
                    TextEntry::make('number_template')
                        ->label('Template Nomor')
                        ->placeholder('— pakai template global PR-YYYYMM-NNNN')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->icon(Heroicon::OutlinedHashtag)
                        ->helperText(fn (ProcurementCategory $record): string => $record->number_template
                            ? 'Template custom dari DB: '.$record->number_template
                            : 'Menggunakan penomoran global.'),
                    TextEntry::make('requirements_summary')
                        ->label('Ringkasan Wajib')
                        ->state(function (ProcurementCategory $record): string {
                            $active = collect(ProcurementCategoryConfiguration::flagLabels())
                                ->filter(fn (string $label, string $field): bool => (bool) $record->{$field})
                                ->map(fn (string $label): string => str_replace('Wajib ', '', $label))
                                ->implode(', ');

                            return $active !== '' ? $active : 'Tidak ada syarat wajib';
                        })
                        ->badge()
                        ->color('gray')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Status & Statistik')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->schema([
                    IconEntry::make('is_active')
                        ->label('Aktif')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->falseIcon(Heroicon::OutlinedXCircle),
                    TextEntry::make('disabled_at')
                        ->label('Dinonaktifkan')
                        ->dateTime('d M Y H:i')
                        ->placeholder('— Masih aktif')
                        ->icon(Heroicon::OutlinedNoSymbol),
                    TextEntry::make('items_count')
                        ->label('Jumlah Items')
                        ->state(fn (ProcurementCategory $record): string => (string) $record->items()->count())
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedCube),
                    TextEntry::make('fields_count')
                        ->label('Jumlah Fields')
                        ->state(fn (ProcurementCategory $record): string => (string) $record->fields()->count())
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedQueueList),
                    TextEntry::make('purchase_requests_count')
                        ->label('Jumlah PR')
                        ->state(fn (ProcurementCategory $record): string => (string) $record->purchaseRequests()->count())
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedClipboardDocumentList),
                    TextEntry::make('usage_status')
                        ->label('Status Pakai')
                        ->state(fn (ProcurementCategory $record): string => $record->items()->exists() || $record->fields()->exists() || $record->purchaseRequests()->exists() ? 'Terpakai — tidak bisa dihapus' : 'Kosong — aman dihapus')
                        ->badge()
                        ->color(fn (ProcurementCategory $record): string => $record->items()->exists() || $record->fields()->exists() || $record->purchaseRequests()->exists() ? 'warning' : 'success')
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
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Riwayat Perubahan')
                ->icon(Heroicon::OutlinedClock)
                ->description('5 aktivitas terakhir • log_name=default')
                ->schema([
                    TextEntry::make('activity_timeline')
                        ->hiddenLabel()
                        ->html()
                        ->state(function (ProcurementCategory $record): string {
                            $activities = Activity::query()
                                ->where('subject_type', ProcurementCategory::class)
                                ->where('subject_id', $record->getKey())
                                ->latest()
                                ->limit(5)
                                ->get();

                            if ($activities->isEmpty()) {
                                return '<div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada perubahan tercatat.</div>';
                            }

                            $labels = [
                                'code' => 'Kode',
                                'name' => 'Nama',
                                'description' => 'Deskripsi',
                                'type' => 'Tipe',
                                'requires_batch' => 'Wajib batch',
                                'requires_jamaah' => 'Wajib jamaah',
                                'requires_vendor' => 'Wajib vendor',
                                'requires_quotation' => 'Wajib quotation',
                                'requires_recommendation_reason' => 'Wajib alasan rekomendasi',
                                'requires_recommendation_evidence' => 'Wajib bukti rekomendasi',
                                'requires_receipt' => 'Wajib receipt',
                                'requires_invoice' => 'Wajib invoice',
                                'requires_po' => 'Wajib PO',
                                'workflow_reference' => 'Workflow',
                                'number_template' => 'Template nomor',
                                'is_active' => 'Aktif',
                                'disabled_at' => 'Dinonaktifkan',
                            ];

                            $formatValue = function (string $key, mixed $val): string {
                                if ($val === null || $val === '') {
                                    return '<span class="text-gray-400 dark:text-gray-500">—</span>';
                                }
                                if (is_bool($val)) {
                                    return $val ? 'Ya' : 'Tidak';
                                }
                                if (in_array($key, ['requires_batch', 'requires_jamaah', 'requires_vendor', 'requires_quotation', 'requires_recommendation_reason', 'requires_recommendation_evidence', 'requires_receipt', 'requires_invoice', 'requires_po', 'is_active'], true)) {
                                    return $val ? 'Ya' : 'Tidak';
                                }
                                if (is_array($val)) {
                                    return e(json_encode($val, JSON_UNESCAPED_UNICODE));
                                }
                                $str = (string) $val;
                                if (str_contains($key, 'disabled_at') && strtotime($str)) {
                                    return e(Carbon::parse($str)->format('d M Y H:i'));
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
