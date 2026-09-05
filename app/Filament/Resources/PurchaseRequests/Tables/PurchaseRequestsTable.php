<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Tables;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\ProcurementRequestSubmitter;
use App\Services\WorkflowStageService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class PurchaseRequestsTable
{
    public static function configure(Table $table, bool $filterByRequester = true): Table
    {
        $table = $table;

        if ($filterByRequester) {
            $table->modifyQueryUsing(fn (Builder $query): Builder => $query->where('requester_id', auth()->id() ?? 0));
        }

        return $table
            ->columns([
                TextColumn::make('pr_number')
                    ->label('Nomor PR')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->copyMessage('Disalin')
                    ->tooltip('Klik untuk salin')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn (PurchaseRequest $record): ?string => $record->title)
                    ->placeholder('— tanpa judul')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->extraAttributes(['class' => 'min-w-[220px] max-w-[320px] whitespace-nowrap'])
                    ->wrap(false),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->tooltip(fn (PurchaseRequest $record): ?string => $record->category?->name.($record->category?->workflow_reference ? ' ('.$record->category->workflow_reference.')' : ''))
                    ->icon(Heroicon::OutlinedTag)
                    ->extraAttributes(['class' => 'min-w-[150px] max-w-[180px] whitespace-nowrap'])
                    ->wrap(false)
                    ->headerFilter(
                        SelectFilter::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ),

                TextColumn::make('office.name')
                    ->label('Kantor')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->extraAttributes(['class' => 'min-w-[180px] whitespace-nowrap'])
                    ->wrap(false)
                    ->headerFilter(
                        SelectFilter::make('office_id')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ),

                TextColumn::make('requester.name')
                    ->label('Pengaju')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->tooltip(fn (PurchaseRequest $record): ?string => $record->requester?->email)
                    ->icon(Heroicon::OutlinedUser)
                    ->extraAttributes(['class' => 'min-w-[150px] max-w-[180px] whitespace-nowrap'])
                    ->wrap(false),

                TextColumn::make('workflow')
                    ->label('Workflow')
                    ->state(fn (PurchaseRequest $record): string => $record->category?->workflow_reference ?? '— default')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::OutlinedCog6Tooth),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
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
                        if (app(WorkflowStageService::class)->isDynamicStage($state, $record)) {
                            return 'info';
                        }

                        return 'info';
                    })
                    ->formatStateUsing(function (string $state, PurchaseRequest $record): string {
                        $label = app(WorkflowStageService::class)->labelFor($state, $record);
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
                            default => $label,
                        };
                    })
                    ->icon(fn (string $state): Heroicon|string => match ($state) {
                        PurchaseRequest::STATUS_DRAFT => Heroicon::OutlinedPencilSquare,
                        PurchaseRequest::STATUS_RETURNED => Heroicon::OutlinedArrowUturnLeft,
                        PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED => Heroicon::OutlinedCheckCircle,
                        PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_CANCELLED => Heroicon::OutlinedXCircle,
                        default => Heroicon::OutlinedClock,
                    })
                    ->headerFilter(
                        SelectFilter::make('status')
                            ->options(fn (): array => self::statusOptions())
                            ->searchable()
                            ->native(false),
                    ),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium')
                    ->tooltip(fn (PurchaseRequest $record): string => $record->items()->count().' item(s)')
                    ->color('gray')
                    ->extraAttributes(['class' => 'whitespace-nowrap'])
                    ->wrap(false),

                TextColumn::make('priority')
                    ->label('Prioritas')
                    ->badge()
                    ->sortable()
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
                    })
                    ->icon(fn (?string $state): Heroicon|string => match ($state) {
                        'urgent' => Heroicon::OutlinedExclamationTriangle,
                        'high' => Heroicon::OutlinedExclamationCircle,
                        default => Heroicon::OutlinedFlag,
                    })
                    ->headerFilter(
                        SelectFilter::make('priority')
                            ->options([
                                'low' => 'Rendah',
                                'normal' => 'Normal',
                                'high' => 'Tinggi',
                                'urgent' => 'Mendesak',
                            ])
                            ->native(false),
                    ),

                TextColumn::make('required_date')
                    ->label('Tgl Dibutuhkan')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn (PurchaseRequest $record): string => PurchaseRequestResource::getUrl('view', ['record' => $record]))
            ->filtersLayout(FiltersLayout::Hidden)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat')
                        ->color('gray'),
                    EditAction::make()
                        ->color('primary')
                        ->visible(fn (PurchaseRequest $record): bool => $record->isCorrectable()),
                    Action::make('submit')
                        ->label('Ajukan')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Ajukan Purchase Request?')
                        ->modalDescription('Request akan dikirim ke Pengadaan untuk review dan approval Keuangan.')
                        ->visible(fn (PurchaseRequest $record): bool => $record->isCorrectable())
                        ->authorize('submit')
                        ->action(function (PurchaseRequest $record): PurchaseRequest {
                            try {
                                $submitted = app(ProcurementRequestSubmitter::class)->submit($record);

                                Notification::make()
                                    ->title('PR berhasil diajukan')
                                    ->body('PR diteruskan ke workflow persetujuan.')
                                    ->success()
                                    ->send();

                                return $submitted;
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('PR belum dapat diajukan')
                                    ->body(collect($exception->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();

                                return $record;
                            } catch (AuthorizationException $exception) {
                                Notification::make()
                                    ->title('Aksi submit tidak diizinkan')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return $record;
                            }
                        }),
                    DeleteAction::make()
                        ->visible(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequest::STATUS_DRAFT),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('Aksi')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('deleteAny', PurchaseRequest::class) ?? false),
                ]),
            ])
            ->emptyStateHeading('Belum ada Purchase Request')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        $standardStatuses = [
            PurchaseRequest::STATUS_DRAFT => 'Draft',
            PurchaseRequest::STATUS_SUBMITTED => 'Diajukan',
            PurchaseRequest::STATUS_PROCUREMENT_REVIEW => 'Review Pengadaan',
            PurchaseRequest::STATUS_PENDING_APPROVAL => 'Menunggu Persetujuan',
            PurchaseRequest::STATUS_APPROVED => 'Disetujui',
            PurchaseRequest::STATUS_REJECTED => 'Ditolak',
            PurchaseRequest::STATUS_RETURNED => 'Dikembalikan',
            PurchaseRequest::STATUS_COMPLETED => 'Selesai',
            PurchaseRequest::STATUS_CANCELLED => 'Dibatalkan',
        ];
        $dynamicStatuses = PurchaseRequest::query()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->filter(fn (string $status): bool => ! isset($standardStatuses[$status]) && preg_match('/^[a-z0-9_]+$/', $status) === 1)
            ->mapWithKeys(fn (string $status): array => [$status => app(WorkflowStageService::class)->labelFor($status)])
            ->all();

        return [...$standardStatuses, ...$dynamicStatuses];
    }
}
