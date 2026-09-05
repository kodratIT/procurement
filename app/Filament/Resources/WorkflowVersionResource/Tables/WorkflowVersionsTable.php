<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Tables;

use App\Enums\WorkflowVersionStatus;
use App\Filament\Resources\WorkflowVersionResource;
use App\Filament\Resources\WorkflowVersionResource\Schemas\WorkflowVersionInfolist;
use App\Models\WorkflowVersion;
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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkflowVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflow.name')
                    ->label('Workflow')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->description(fn (WorkflowVersion $record): ?string => $record->workflow?->code)
                    ->placeholder('—')
                    ->copyable()
                    ->tooltip('Klik untuk salin'),

                TextColumn::make('version_number')
                    ->label('Versi')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn (mixed $state): string => 'v'.$state),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'draft',
                        'success' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'active',
                        'warning' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'retired',
                    ])
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state instanceof WorkflowVersionStatus ? $state->value : (string) $state),

                TextColumn::make('steps_count')
                    ->label('Tahap')
                    ->counts('steps')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('effective')
                    ->label('Periode Efektif')
                    ->state(fn (WorkflowVersion $record): string => trim(($record->effective_from?->format('d M Y') ?? '—').' → '.($record->effective_until?->format('d M Y') ?? '∞')))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->toggleable(),

                IconColumn::make('isUsed')
                    ->label('Dipakai')
                    ->state(fn (WorkflowVersion $record): bool => $record->isUsed())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedLockClosed)
                    ->falseIcon(Heroicon::OutlinedLockOpen)
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success')
                    ->tooltip(fn (WorkflowVersion $record): string => $record->isUsed() ? 'Immutable — sudah dipakai PR' : 'Masih bisa edit')
                    ->alignCenter(),

                TextColumn::make('activated_at')
                    ->label('Aktif Sejak')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('version_number', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (WorkflowVersion $record): string => WorkflowVersionResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('workflow_id')
                    ->label('Workflow')
                    ->relationship('workflow', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WorkflowVersionStatus::options()),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading('Detail Workflow Version')
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => WorkflowVersionInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at', 'activated_at', 'retired_at'])
                        ->beforeReplicaSaved(function (WorkflowVersion $replica): void {
                            $replica->version_number = $replica->version_number + 1;
                            $replica->status = WorkflowVersionStatus::Draft;
                            $replica->activated_at = null;
                            $replica->retired_at = null;
                        })
                        ->successNotificationTitle('Versi diduplikasi sebagai draft'),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (WorkflowVersion $record): bool => $record->status !== WorkflowVersionStatus::Active && ! $record->isUsed())
                        ->requiresConfirmation()
                        ->action(function (WorkflowVersion $record): void {
                            $record->activate();
                        }),
                    Action::make('retire')
                        ->label('Pensiunkan')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('warning')
                        ->visible(fn (WorkflowVersion $record): bool => $record->status === WorkflowVersionStatus::Active)
                        ->requiresConfirmation()
                        ->action(fn (WorkflowVersion $record): bool => $record->retire()),
                    DeleteAction::make()
                        ->visible(fn (WorkflowVersion $record): bool => ! $record->isUsed()),
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
            ->emptyStateHeading('Belum ada Workflow Version')
            ->emptyStateDescription('Buat versi untuk workflow. Versi draft harus punya steps berurutan 1..n sebelum diaktifkan.')
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }
}
