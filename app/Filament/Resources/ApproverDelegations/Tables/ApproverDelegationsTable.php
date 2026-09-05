<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Tables;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use App\Filament\Resources\ApproverDelegations\Schemas\ApproverDelegationInfolist;
use App\Models\ApproverDelegation;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ApproverDelegationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delegator.name')
                    ->label('Original approver')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedUser)
                    ->description(fn (ApproverDelegation $record): ?string => $record->delegator?->email)
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin'),

                TextColumn::make('delegate.name')
                    ->label('Delegate')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->description(fn (ApproverDelegation $record): ?string => $record->delegate?->email)
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin'),

                TextColumn::make('validity')
                    ->label('Masa Berlaku')
                    ->state(fn (ApproverDelegation $record): string => trim(($record->valid_from?->format('d M Y') ?? '—').' → '.($record->valid_until?->format('d M Y') ?? '∞')))
                    ->description(fn (ApproverDelegation $record): string => $record->isActiveAt() ? 'Sedang aktif' : 'Tidak aktif / kadaluarsa')
                    ->color(fn (ApproverDelegation $record): string => $record->isActiveAt() ? 'success' : 'danger')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('valid_from', $direction))
                    ->toggleable(),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50)
                    ->wrap()
                    ->searchable()
                    ->placeholder('—')
                    ->tooltip(fn (ApproverDelegation $record): ?string => $record->reason),

                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter()
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ApproverDelegation $record): string => $record->isActiveAt() ? 'Aktif' : 'Non-aktif')
                    ->colors([
                        'success' => 'Aktif',
                        'danger' => 'Non-aktif',
                    ])
                    ->icon(fn (ApproverDelegation $record): Heroicon|string => $record->isActiveAt() ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->toggleable(),

                TextColumn::make('overlap')
                    ->label('Overlap')
                    ->badge()
                    ->state(function (ApproverDelegation $record): string {
                        $exists = ApproverDelegation::query()
                            ->where('id', '!=', $record->getKey())
                            ->where('delegator_id', $record->delegator_id)
                            ->where('is_active', true)
                            ->whereDate('valid_from', '<=', $record->valid_until)
                            ->whereDate('valid_until', '>=', $record->valid_from)
                            ->exists();

                        return $exists ? 'Tumpang Tindih' : 'Aman';
                    })
                    ->colors([
                        'danger' => 'Tumpang Tindih',
                        'success' => 'Aman',
                    ])
                    ->icon(fn (string $state): Heroicon|string => $state === 'Tumpang Tindih' ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                    ->tooltip(fn (ApproverDelegation $record): string => $record->is_active ? 'Cek delegasi lain dengan delegator & periode sama' : 'Non-aktif tidak dihitung overlap')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('valid_from', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (ApproverDelegation $record): string => ApproverDelegationResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('delegator_id')
                    ->label('Original approver')
                    ->relationship('delegator', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('delegate_id')
                    ->label('Delegate')
                    ->relationship('delegate', 'name')
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
                Filter::make('expiring_soon')
                    ->label('Akan habis ≤ 7 hari')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('valid_until')->whereDate('valid_until', '>=', Carbon::today())->whereDate('valid_until', '<=', Carbon::today()->addDays(7)))
                    ->toggle(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewModal')
                        ->label('Lihat')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->modalHeading('Detail Approver Delegation')
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->schema(fn (Schema $schema): Schema => ApproverDelegationInfolist::configure($schema)),
                    EditAction::make()->color('primary'),
                    ReplicateAction::make()
                        ->label('Duplikat')
                        ->color('info')
                        ->excludeAttributes(['created_at', 'updated_at'])
                        ->beforeReplicaSaved(function (ApproverDelegation $replica): void {
                            $replica->is_active = false;
                        })
                        ->successNotificationTitle('Delegasi diduplikasi (non-aktif)'),
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
            ->emptyStateHeading('Belum ada Approver Delegation')
            ->emptyStateDescription('Buat delegasi pertama untuk menggantikan approver yang sedang cuti / tidak aktif.')
            ->emptyStateIcon(Heroicon::OutlinedArrowPath);
    }
}
