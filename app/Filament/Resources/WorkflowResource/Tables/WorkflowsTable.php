<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Tables;

use App\Filament\Resources\WorkflowResource;
use App\Filament\Resources\WorkflowResource\Schemas\WorkflowInfolist;
use App\Models\Workflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class WorkflowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->badge()
                    ->color('gray')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin')
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->description(fn (Workflow $record): ?string => $record->description ? str($record->description)->limit(50)->toString() : null)
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('versions_count')
                    ->label('Versi')
                    ->counts('versions')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('active_version')
                    ->label('Versi Aktif')
                    ->state(fn (Workflow $record): string => $record->activeVersion()?->version_number ? 'v'.$record->activeVersion()->version_number : '—')
                    ->description(fn (Workflow $record): ?string => $record->activeVersion()?->status->value ?? 'Belum ada')
                    ->badge()
                    ->color(fn (Workflow $record): string => $record->activeVersion() ? 'success' : 'gray')
                    ->alignCenter(),

                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter()
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (Workflow $record): string => WorkflowResource::getUrl('view', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Non-aktif'),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading('Detail Workflow')
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => WorkflowInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at'])
                        ->beforeReplicaSaved(function (Workflow $replica): void {
                            $replica->code = $replica->code.'-copy';
                            $replica->is_active = false;
                        })
                        ->successNotificationTitle('Workflow diduplikasi (non-aktif)'),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (Workflow $record): bool => ! $record->is_active)
                        ->authorize(fn (Workflow $record): bool => Gate::allows('activate', $record))
                        ->action(fn (Workflow $record): bool => $record->activate()),
                    Action::make('retire')
                        ->label('Pensiunkan')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Workflow $record): bool => $record->is_active)
                        ->authorize(fn (Workflow $record): bool => Gate::allows('retire', $record))
                        ->action(fn (Workflow $record): bool => $record->retire()),
                    DeleteAction::make()
                        ->visible(fn (Workflow $record): bool => ! $record->versions()->whereHas('approvalInstances')->exists()),
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
            ->emptyStateHeading('Belum ada Workflow')
            ->emptyStateDescription('Buat workflow pertama. Kode dipakai di kategori untuk binding approval.')
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }
}
