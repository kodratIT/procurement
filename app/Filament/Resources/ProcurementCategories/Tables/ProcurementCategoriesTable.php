<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Tables;

use App\Enums\ProcurementCategoryType;
use App\Filament\Resources\ProcurementCategories\Schemas\ProcurementCategoryInfolist;
use App\Filament\Resources\ProcurementCategoryResource;
use App\Models\ProcurementCategory;
use App\Models\Workflow;
use App\Support\ProcurementCategoryConfiguration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ProcurementCategoriesTable
{
    public static function configure(Table $table): Table
    {
        $flagLabels = ProcurementCategoryConfiguration::flagLabels();
        $flagIconColumns = [];
        foreach ($flagLabels as $field => $label) {
            $flagIconColumns[] = IconColumn::make($field)
                ->label(str_replace('Wajib ', '', $label))
                ->boolean()
                ->trueIcon(Heroicon::OutlinedCheckCircle)
                ->falseIcon(Heroicon::OutlinedXCircle)
                ->alignCenter()
                ->toggleable(isToggledHiddenByDefault: true);
        }

        // Cache workflow map untuk formatStateUsing tanpa N+1
        $workflowMap = Workflow::query()->pluck('name', 'code')->all();

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedTag)
                    ->description(fn (ProcurementCategory $record): ?string => $record->description ? str($record->description)->limit(45)->toString() : null)
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'info' => ProcurementCategoryType::Goods->value,
                        'success' => ProcurementCategoryType::Service->value,
                        'warning' => ProcurementCategoryType::GoodsAndServices->value,
                    ])
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ProcurementCategoryType
                        ? $state->label()
                        : (ProcurementCategoryType::tryFrom((string) $state)?->label() ?? (string) $state)),

                TextColumn::make('requirements_summary')
                    ->label('Kelengkapan')
                    ->badge()
                    ->state(function (ProcurementCategory $record): string {
                        $count = collect(array_keys(ProcurementCategoryConfiguration::flagLabels()))
                            ->filter(fn (string $field): bool => (bool) $record->{$field})
                            ->count();

                        return $count === 0 ? 'Tanpa wajib' : $count.' wajib';
                    })
                    ->description(function (ProcurementCategory $record): string {
                        $active = collect(ProcurementCategoryConfiguration::flagLabels())
                            ->filter(fn (string $label, string $field): bool => (bool) $record->{$field})
                            ->map(fn (string $label): string => str_replace('Wajib ', '', $label))
                            ->implode(', ');

                        return $active !== '' ? $active : 'Tidak ada syarat wajib';
                    })
                    ->colors([
                        'gray' => fn (string $state): bool => $state === 'Tanpa wajib',
                        'warning' => fn (string $state): bool => str_contains($state, 'wajib') && (int) str($state)->before(' ')->toString() <= 3,
                        'success' => fn (string $state): bool => (int) str($state)->before(' ')->toString() > 3,
                    ])
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->toggleable(),

                ...$flagIconColumns,

                TextColumn::make('workflow_reference')
                    ->label('Workflow')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null || $state === '' ? 'gray' : 'info')
                    ->searchable()
                    ->placeholder('— default')
                    ->description(fn (ProcurementCategory $record): ?string => $record->workflow_reference === null || $record->workflow_reference === ''
                        ? 'fallback standard-procurement'
                        : ($workflowMap[$record->workflow_reference] ?? null))
                    ->formatStateUsing(fn (?string $state): string => $state === null || $state === ''
                        ? '— default'
                        : (isset($workflowMap[$state]) ? $state.' — '.$workflowMap[$state] : $state))
                    ->toggleable()
                    ->copyable()
                    ->icon(Heroicon::OutlinedCog6Tooth),

                TextColumn::make('number_template')
                    ->label('Template Nomor')
                    ->placeholder('— global')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->copyable()
                    ->limit(25)
                    ->description(fn (ProcurementCategory $record): ?string => $record->number_template ? 'dari DB • '.str($record->number_template)->limit(30)->toString() : 'pakai PR-YYYYMM-NNNN')
                    ->tooltip(fn (ProcurementCategory $record): ?string => $record->number_template),

                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter()
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ProcurementCategory $record): string => $record->is_active ? 'Aktif' : 'Non-aktif')
                    ->colors([
                        'success' => 'Aktif',
                        'danger' => 'Non-aktif',
                    ])
                    ->icon(fn (ProcurementCategory $record): Heroicon|string => $record->is_active ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->toggleable(),

                TextColumn::make('disabled_at')
                    ->label('Dinonaktifkan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Fields')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('purchase_requests_count')
                    ->counts('purchaseRequests')
                    ->label('PR')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('usage')
                    ->label('Terpakai')
                    ->badge()
                    ->state(function (ProcurementCategory $record): string {
                        $used = $record->items_count > 0 || $record->fields_count > 0 || $record->purchase_requests_count > 0
                            || $record->items()->exists() || $record->fields()->exists() || $record->purchaseRequests()->exists();

                        // Fallback when counts not loaded
                        if (! isset($record->items_count)) {
                            $used = $record->items()->exists() || $record->fields()->exists() || $record->purchaseRequests()->exists();
                        }

                        return $used ? 'Terpakai' : 'Kosong';
                    })
                    ->colors([
                        'warning' => 'Terpakai',
                        'gray' => 'Kosong',
                    ])
                    ->icon(fn (string $state): Heroicon|string => $state === 'Terpakai' ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                    ->tooltip(fn (ProcurementCategory $record): string => $record->items()->exists() || $record->fields()->exists() || $record->purchaseRequests()->exists()
                        ? 'Tidak bisa dihapus, sudah punya relasi'
                        : 'Aman untuk dihapus')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (ProcurementCategory $record): string => ProcurementCategoryResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(ProcurementCategoryType::options()),
                TernaryFilter::make('is_active')
                    ->label('Status aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Non-aktif'),
                SelectFilter::make('workflow_reference')
                    ->label('Workflow')
                    ->options(function (): array {
                        $categoryOptions = ProcurementCategory::query()
                            ->whereNotNull('workflow_reference')
                            ->where('workflow_reference', '!=', '')
                            ->distinct()
                            ->pluck('workflow_reference', 'workflow_reference')
                            ->all();

                        $workflowOptions = Workflow::query()
                            ->orderBy('code')
                            ->pluck('name', 'code')
                            ->mapWithKeys(fn (string $name, string $code): array => [$code => $code.' — '.$name])
                            ->all();

                        return $workflowOptions + $categoryOptions + ['' => '— default (fallback)'];
                    })
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('requires_batch')
                    ->label('Wajib batch')
                    ->placeholder('Semua'),
                TernaryFilter::make('requires_quotation')
                    ->label('Wajib quotation')
                    ->placeholder('Semua'),
                TernaryFilter::make('requires_vendor')
                    ->label('Wajib vendor')
                    ->placeholder('Semua'),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading(fn (ProcurementCategory $record): string => 'Detail Kategori: '.$record->name)
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => ProcurementCategoryInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at', 'disabled_at'])
                        ->beforeReplicaSaved(function (ProcurementCategory $replica): void {
                            $replica->code = $replica->code.'-COPY';
                            $replica->name = $replica->name.' (Copy)';
                            $replica->is_active = false;
                            $replica->disabled_at = now();
                        })
                        ->successNotificationTitle('Kategori diduplikasi (non-aktif)'),
                    Action::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (ProcurementCategory $record): bool => $record->is_active)
                        ->authorize('deactivate')
                        ->action(fn (ProcurementCategory $record): bool => $record->deactivate()),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (ProcurementCategory $record): bool => ! $record->is_active)
                        ->authorize('activate')
                        ->action(fn (ProcurementCategory $record): bool => $record->activate()),
                    DeleteAction::make(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('Aksi')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Kategori')
            ->emptyStateDescription('Buat kategori pertama untuk mengelompokkan barang/jasa procurement. Kategori dipakai di PR, workflow, dan penomoran.')
            ->emptyStateIcon(Heroicon::OutlinedTag);
    }
}
