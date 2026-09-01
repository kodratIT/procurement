<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

final class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('purchase_order_id')
                ->label('Approved purchase order')
                ->options(fn (): array => self::purchaseOrderOptions())
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('invoice_number')
                ->label('Invoice number')
                ->required()
                ->maxLength(100),
            TextInput::make('total_amount')
                ->label('Invoice total')
                ->numeric()
                ->minValue(0.01)
                ->required(),
            DatePicker::make('due_date')->label('Due date')->required(),
            Repeater::make('lines')
                ->label('Invoice lines')
                ->schema([
                    Select::make('purchase_order_item_id')
                        ->label('PO line')
                        ->options(fn (Get $get): array => self::lineOptions($get('purchase_order_id')))
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                    TextInput::make('unit_price')->numeric()->minValue(0)->required(),
                    TextInput::make('description')->maxLength(255),
                ])
                ->columns(4)
                ->defaultItems(0),
            FileUpload::make('attachments')
                ->label('Invoice evidence')
                ->multiple()
                ->storeFiles(false)
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024))
                ->required(),
            Textarea::make('notes')->label('Notes')->maxLength(10000)->columnSpanFull(),
        ]);
    }

    /** @return array<int|string, string> */
    private static function purchaseOrderOptions(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        return app(MultiOfficeAuthorization::class)->scopeForUser(
            PurchaseOrder::query()
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED])
                ->with('vendor')
                ->orderByDesc('updated_at'),
            $user,
            ProcurementPermissions::MANAGE_FINANCE,
        )->get(['id', 'po_number', 'vendor_id'])->mapWithKeys(fn (PurchaseOrder $order): array => [
            $order->id => $order->po_number.' — '.($order->vendor?->name ?? 'Unknown vendor'),
        ])->all();
    }

    /** @return array<int|string, string> */
    private static function lineOptions(mixed $purchaseOrderId): array
    {
        if (! is_numeric($purchaseOrderId)) {
            return [];
        }

        return PurchaseOrder::query()->with('items')->find((int) $purchaseOrderId)?->items
            ->mapWithKeys(fn ($item): array => [
                $item->id => sprintf('%s — ordered %s %s', $item->item_name, $item->quantity, $item->unit_name),
            ])->all() ?? [];
    }
}
