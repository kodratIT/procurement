<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Tables;

use App\Models\Quotation;
use App\Services\QuotationComparisonService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('purchaseRequest.pr_number')->label('PR')->searchable()->sortable(),
                TextColumn::make('quotation_number')->label('Nomor quotation')->searchable()->sortable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable()->sortable(),
                TextColumn::make('total_amount')->label('Total tersimpan')->money('IDR')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('vendor_id')->relationship('vendor', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options([
                    Quotation::STATUS_DRAFT => 'Draft',
                    Quotation::STATUS_SUBMITTED => 'Submitted',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('comparison')
                    ->label('Bandingkan')
                    ->icon(Heroicon::OutlinedScale)
                    ->modalHeading(fn (Quotation $record): string => 'Perbandingan quotation — '.$record->purchaseRequest?->pr_number)
                    ->modalContent(fn (Quotation $record): View => view(
                        'filament.quotation-comparison',
                        ['comparison' => app(QuotationComparisonService::class)->compare($record->purchaseRequest)],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->authorize('view'),
                Action::make('recommend')
                    ->label('Rekomendasikan vendor')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan rekomendasi')
                            ->required(fn (Quotation $record): bool => (bool) $record->purchaseRequest?->category?->requires_recommendation_reason),
                    ])
                    ->requiresConfirmation()
                    ->authorize('recommend')
                    ->action(function (Quotation $record, array $data): Quotation {
                        app(QuotationComparisonService::class)->recommend(
                            $record->purchaseRequest,
                            $record,
                            (string) ($data['reason'] ?? ''),
                        );

                        return $record->refresh();
                    }),
                Action::make('handoff')
                    ->label('Serahkan ke approval')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->authorize('recommend')
                    ->action(fn (Quotation $record): mixed => app(QuotationComparisonService::class)->handoffToApproval($record->purchaseRequest)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize('deleteAny'),
                ]),
            ]);
    }
}
