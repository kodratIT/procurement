<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')->label('Invoice number')->searchable()->sortable(),
                TextColumn::make('purchaseOrder.po_number')->label('PO')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('total_amount')->label('Total')->money('IDR')->sortable(),
                TextColumn::make('payment_total')
                    ->label('Paid')
                    ->money('IDR')
                    ->state(fn (Invoice $record): string => $record->paymentTotal()),
                TextColumn::make('outstanding_amount')
                    ->label('Outstanding')
                    ->money('IDR')
                    ->state(fn (Invoice $record): string => $record->outstandingAmount()),
                TextColumn::make('status')
                    ->label('Payment')
                    ->badge()
                    ->state(fn (Invoice $record): string => $record->paymentStatus())
                    ->sortable(),
                TextColumn::make('match_status')->label('Match')->badge()->sortable(),
                TextColumn::make('review_status')->label('Review')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Invoice::STATUS_UNPAID => 'Unpaid',
                    Invoice::STATUS_PARTIALLY_PAID => 'Partially paid',
                    Invoice::STATUS_PAID => 'Paid',
                ]),
                SelectFilter::make('match_status')->options([
                    Invoice::MATCH_STATUS_MATCHED => 'Matched',
                    Invoice::MATCH_STATUS_MISMATCHED => 'Mismatched',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
