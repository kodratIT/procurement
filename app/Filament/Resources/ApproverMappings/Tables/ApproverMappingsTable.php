<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Tables;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Filament\Resources\ApproverMappings\Schemas\ApproverMappingInfolist;
use App\Models\ApproverMapping;
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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class ApproverMappingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflowStep.name')
                    ->label('Step')
                    ->state(fn (ApproverMapping $record): string => $record->workflowStep !== null ? $record->workflowStep->name.' ('.($record->workflowStep->workflowVersion?->workflow?->name ?? '—').')' : '— Global')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('workflowStep', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhereHas('workflowVersion.workflow', fn (Builder $q2) => $q2->where('name', 'like', "%{$search}%"))))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        WorkflowStep::select('name')->whereColumn('workflow_steps.id', 'approver_mappings.workflow_step_id'), $direction
                    ))
                    ->description(fn (ApproverMapping $record): ?string => $record->workflowStep === null ? 'Semua Workflow (global)' : 'Step #'.$record->workflowStep->sequence.' • '.($record->workflowStep->step_type->value ?? ''))
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin'),

                TextColumn::make('resolver_type')
                    ->label('Resolver')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->colors([
                        'info' => 'role_in_request_office',
                        'primary' => 'role_in_budget_owner_office',
                        'warning' => 'specific_user',
                        'success' => fn (string $state): bool => in_array($state, ['department_head', 'branch_head', 'cost_center_owner'], true),
                        'gray' => 'nominal_role',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'role_in_request_office' => 'Role Pemohon',
                        'role_in_budget_owner_office' => 'Role Budget',
                        'specific_user' => 'Orang Tertentu',
                        'department_head' => 'Kepala Dept',
                        'branch_head' => 'Kepala Cabang',
                        'cost_center_owner' => 'Owner CC',
                        'nominal_role' => 'Nominal Role',
                        default => str($state)->headline()->toString(),
                    }),

                TextColumn::make('approver')
                    ->label('Approver')
                    ->state(fn (ApproverMapping $record): string => $record->user?->name ?? $record->role?->name ?? '—')
                    ->description(fn (ApproverMapping $record): ?string => $record->user !== null && $record->role !== null ? $record->role->name : ($record->user !== null ? 'Specific User' : ($record->role !== null ? $record->role->code : null)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('role', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%")))
                    ->icon(fn (ApproverMapping $record): string|Heroicon => $record->user !== null ? Heroicon::OutlinedUser : Heroicon::OutlinedUserGroup)
                    ->placeholder('—'),

                TextColumn::make('scope')
                    ->label('Scope Organisasi')
                    ->state(function (ApproverMapping $record): string {
                        $parts = array_filter([
                            $record->office?->name,
                            $record->branch?->name,
                            $record->department?->name,
                            $record->costCenter?->name,
                        ]);

                        return $parts === [] ? '— Semua' : implode(' › ', $parts);
                    })
                    ->description(fn (ApproverMapping $record): string => match ($record->scope_source) {
                        'request_office' => 'Sumber: Kantor Pemohon',
                        'budget_owner_office' => 'Sumber: Kantor Budget',
                        'request_branch' => 'Sumber: Cabang Pemohon',
                        'request_department' => 'Sumber: Dept Pemohon',
                        'request_cost_center' => 'Sumber: CC Pemohon',
                        'configured' => 'Sumber: Konfigurasi',
                        default => $record->scope_source,
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('office', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('branch', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%")))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('fallback_type')
                    ->label('Fallback')
                    ->badge()
                    ->colors([
                        'danger' => 'block',
                        'warning' => 'role',
                        'info' => 'user',
                    ])
                    ->formatStateUsing(fn (string $state, ApproverMapping $record): string => match ($state) {
                        'block' => 'Block',
                        'role' => '→ '.($record->fallbackRole?->name ?? 'Role'),
                        'user' => '→ '.($record->fallbackUser?->name ?? 'User'),
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Prioritas')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter()
                    ->description(fn (ApproverMapping $record): string => 'Skor: '.($record->priority * 100 + collect(['office_id', 'branch_id', 'department_id', 'cost_center_id'])->filter(fn (string $f): bool => $record->{$f} !== null)->count())),

                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter()
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('validity')
                    ->label('Masa Berlaku')
                    ->state(fn (ApproverMapping $record): string => trim(($record->valid_from?->format('d M Y') ?? '—').' → '.($record->valid_until?->format('d M Y') ?? '∞')))
                    ->description(fn (ApproverMapping $record): string => $record->isActiveAt() ? 'Sedang aktif' : 'Tidak aktif / kadaluarsa')
                    ->color(fn (ApproverMapping $record): string => $record->isActiveAt() ? 'success' : 'danger')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('valid_from', $direction))
                    ->toggleable(),

                IconColumn::make('allow_self_approval')
                    ->label('Self')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Self Approval'),

                TextColumn::make('conflict')
                    ->label('Konflik')
                    ->badge()
                    ->state(function (ApproverMapping $record): string {
                        $exists = ApproverMapping::query()
                            ->where('id', '!=', $record->getKey())
                            ->where('resolver_type', $record->resolver_type)
                            ->where('workflow_step_id', $record->workflow_step_id)
                            ->where('office_id', $record->office_id)
                            ->where('branch_id', $record->branch_id)
                            ->where('department_id', $record->department_id)
                            ->where('cost_center_id', $record->cost_center_id)
                            ->where('is_active', true)
                            ->whereBetween('priority', [$record->priority - 1, $record->priority + 1])
                            ->exists();

                        return $exists ? 'Bentrok' : 'Aman';
                    })
                    ->colors([
                        'danger' => 'Bentrok',
                        'success' => 'Aman',
                    ])
                    ->icon(fn (string $state): Heroicon|string => $state === 'Bentrok' ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                    ->tooltip(fn (ApproverMapping $record): string => $record->is_active ? 'Cek mapping lain dengan scope & priority sama' : 'Non-aktif tidak dihitung konflik')
                    ->toggleable(),
            ])
            ->defaultSort('priority', 'desc')
            ->reorderable('priority')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (ApproverMapping $record): string => ApproverMappingResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['workflowStep.workflowVersion.workflow', 'role', 'user', 'office', 'branch', 'department', 'costCenter']))
            ->filters([
                SelectFilter::make('workflow_step_id')
                    ->label('Step')
                    ->relationship('workflowStep', 'name', fn (Builder $query): Builder => $query->with('workflowVersion.workflow'))
                    ->getOptionLabelFromRecordUsing(fn (WorkflowStep $record): string => $record->name.' ('.($record->workflowVersion?->workflow?->name ?? '—').')')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('resolver_type')
                    ->label('Resolver')
                    ->options([
                        'role_in_request_office' => 'Role Pemohon',
                        'role_in_budget_owner_office' => 'Role Budget',
                        'specific_user' => 'Orang Tertentu',
                        'department_head' => 'Kepala Dept',
                        'branch_head' => 'Kepala Cabang',
                        'cost_center_owner' => 'Owner CC',
                        'nominal_role' => 'Nominal Role',
                    ]),
                SelectFilter::make('scope_source')
                    ->label('Sumber Kantor')
                    ->options([
                        'request_office' => 'Kantor Pemohon',
                        'budget_owner_office' => 'Kantor Budget',
                        'request_branch' => 'Cabang Pemohon',
                        'request_department' => 'Dept Pemohon',
                        'request_cost_center' => 'CC Pemohon',
                        'configured' => 'Konfigurasi',
                    ]),
                SelectFilter::make('fallback_type')
                    ->label('Fallback')
                    ->options([
                        'block' => 'Block',
                        'role' => 'Fallback Role',
                        'user' => 'Fallback User',
                    ]),
                SelectFilter::make('office_id')
                    ->label('Kantor')
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Status aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Non-aktif'),
                Filter::make('expired')
                    ->label('Kadaluwarsa')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('valid_until')->whereDate('valid_until', '<', Carbon::today()))
                    ->toggle(),
                Filter::make('active_today')
                    ->label('Aktif Hari Ini (valid)')
                    ->query(fn (Builder $query): Builder => $query->activeAt(Carbon::today()))
                    ->toggle(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading('Detail Approver Mapping')
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => ApproverMappingInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at'])
                        ->beforeReplicaSaved(function (ApproverMapping $replica): void {
                            $replica->is_active = false;
                            $replica->priority = $replica->priority + 1;
                        })
                        ->successNotificationTitle('Mapping diduplikasi (non-aktif)'),
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
            ->emptyStateHeading('Belum ada Approver Mapping')
            ->emptyStateDescription('Buat mapping pertama untuk menentukan siapa approver di tiap step workflow.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
