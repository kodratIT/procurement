<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderRevisionService;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')->label('PO number')->searchable()->sortable(),
                TextColumn::make('purchaseRequest.pr_number')->label('PR number')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('total_amount')->label('Total')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('receipt_status')
                    ->label('Receipt status')
                    ->state(fn (PurchaseOrder $record): string => app(ReceivingService::class)->status($record))
                    ->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(PurchaseOrder::STATUSES, PurchaseOrder::STATUSES)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (PurchaseOrder $record): bool => $record->isEditableBeforeApproval()),
                Action::make('approve')
                    ->label('Approve')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => $record->isEditableBeforeApproval())
                    ->authorize('approveRevision')
                    ->action(fn (PurchaseOrder $record): PurchaseOrder => app(PurchaseOrderRevisionService::class)->approve($record, auth()->user())),
                Action::make('print')
                    ->label('Print')
                    ->url(fn (PurchaseOrder $record): string => route('purchase-orders.print', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft),
            ]);
    }
}
