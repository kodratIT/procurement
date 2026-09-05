<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Tables;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use App\Filament\Resources\WorkflowStepResource;
use App\Filament\Resources\WorkflowStepResource\Schemas\WorkflowStepInfolist;
use App\Models\WorkflowStep;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkflowStepsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflowVersion.workflow.name')
                    ->label('Workflow')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->description(fn (WorkflowStep $record): ?string => $record->workflowVersion?->workflow?->code ?? '—')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('workflowVersion.version_number')
                    ->label('Versi')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn (mixed $state): string => $state !== null ? 'v'.$state : '—'),

                TextColumn::make('sequence')
                    ->label('Urutan')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('name')
                    ->label('Tahap')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin'),

                TextColumn::make('step_type')
                    ->label('Tipe')
                    ->badge()
                    ->colors([
                        'primary' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'approval',
                        'info' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'review',
                        'gray' => fn (mixed $state): bool => in_array($state instanceof \BackedEnum ? $state->value : (string) $state, ['informational', 'conditional', 'final_approval'], true),
                    ])
                    ->sortable(),

                TextColumn::make('approval_mode')
                    ->label('Mode')
                    ->badge()
                    ->colors([
                        'success' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'sequential',
                        'warning' => fn (mixed $state): bool => str_starts_with($state instanceof \BackedEnum ? $state->value : (string) $state, 'parallel'),
                    ])
                    ->toggleable(),

                TextColumn::make('resolver_type')
                    ->label('Resolver')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->placeholder('— nominal')
                    ->toggleable(),

                TextColumn::make('conditions_count')
                    ->label('Kondisi')
                    ->counts('conditions')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('mappings_count')
                    ->label('Mappings')
                    ->counts('approverMappings')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                IconColumn::make('is_required')
                    ->label('Wajib')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->alignCenter(),

                TextColumn::make('sla_minutes')
                    ->label('SLA')
                    ->sortable()
                    ->placeholder('—')
                    ->state(fn (WorkflowStep $record): string => $record->sla_minutes !== null ? $record->sla_minutes.'m' : '—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('workflowVersion.status')
                    ->label('Status Versi')
                    ->badge()
                    ->colors([
                        'gray' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'draft',
                        'success' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'active',
                        'warning' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'retired',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sequence', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (WorkflowStep $record): string => WorkflowStepResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('workflow_version_id')
                    ->label('Versi')
                    ->relationship('workflowVersion', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => ($record->workflow?->name ?? '—').' v'.$record->version_number)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('step_type')
                    ->label('Tipe')
                    ->options(WorkflowStepType::options()),
                SelectFilter::make('approval_mode')
                    ->label('Mode')
                    ->options(WorkflowApprovalMode::options()),
                SelectFilter::make('resolver_type')
                    ->label('Resolver')
                    ->options([
                        'role_in_request_office' => 'Role Pemohon',
                        'role_in_budget_owner_office' => 'Role Budget',
                        'specific_user' => 'Orang Tertentu',
                        'department_head' => 'Kepala Dept',
                        'branch_head' => 'Kepala Cabang',
                    ]),
                TernaryFilter::make('is_required')
                    ->label('Wajib')
                    ->placeholder('Semua')
                    ->trueLabel('Wajib')
                    ->falseLabel('Opsional'),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading('Detail Workflow Stage')
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => WorkflowStepInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at'])
                        ->beforeReplicaSaved(function (WorkflowStep $replica): void {
                            $replica->sequence = $replica->sequence + 1;
                        })
                        ->successNotificationTitle('Tahap diduplikasi'),
                    DeleteAction::make()
                        ->visible(fn (WorkflowStep $record): bool => ! ($record->workflowVersion?->isUsed() ?? false)),
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
            ->emptyStateHeading('Belum ada Workflow Stage')
            ->emptyStateDescription('Buat tahap untuk versi workflow. Urutan harus 1..n tanpa lubang.')
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }
}
