<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Services\InvoiceMatchingService;
use App\Services\ReceivingService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')
                ->schema([
                    TextEntry::make('invoice_number')->label('Invoice number'),
                    TextEntry::make('purchaseOrder.po_number')->label('Purchase order'),
                    TextEntry::make('vendor.name')->label('Vendor'),
                    TextEntry::make('total_amount')->label('Total')->money('IDR'),
                    TextEntry::make('payment_total')
                        ->label('Paid to date')
                        ->money('IDR')
                        ->state(fn (Invoice $record): string => $record->paymentTotal()),
                    TextEntry::make('outstanding_amount')
                        ->label('Outstanding balance')
                        ->money('IDR')
                        ->state(fn (Invoice $record): string => $record->outstandingAmount()),
                    TextEntry::make('due_date')->label('Due date')->date(),
                    TextEntry::make('status')
                        ->label('Payment status')
                        ->badge()
                        ->state(fn (Invoice $record): string => $record->paymentStatus()),
                    TextEntry::make('match_status')->label('Match status')->badge(),
                    TextEntry::make('mismatch_reason')->label('Mismatch explanation')->placeholder('No mismatch'),
                ])->columns(3),
            Section::make('Purchase order and receipt evidence')
                ->schema([
                    TextEntry::make('purchase_order.total_amount')->label('Approved PO total')->money('IDR'),
                    TextEntry::make('received_total')
                        ->label('Received evidence total')
                        ->money('IDR')
                        ->state(fn (Invoice $record): string => app(InvoiceMatchingService::class)->check($record->purchaseOrder, [
                            'total_amount' => (string) $record->total_amount,
                        ])['received_total']),
                    TextEntry::make('receipt_status')
                        ->label('Receipt status')
                        ->badge()
                        ->state(fn (Invoice $record): string => app(ReceivingService::class)->status($record->purchaseOrder)),
                    TextEntry::make('items')
                        ->label('Matched invoice lines')
                        ->state(fn (Invoice $record): string => $record->items
                            ->map(fn ($item): string => sprintf('%s × %s @ %s = %s', $item->description, $item->quantity, $item->unit_price, $item->line_total))
                            ->implode('; ') ?: 'Amount-only invoice'),
                    TextEntry::make('attachments')
                        ->label('Private evidence')
                        ->state(fn (Invoice $record): string => $record->attachments
                            ->map(fn ($attachment): string => $attachment->original_name.' ('.$attachment->mime_type.')')
                            ->implode('; ') ?: 'No evidence attached'),
                ])->columns(2),
            TextEntry::make('notes')->label('Notes')->columnSpanFull(),
        ]);
    }
}
