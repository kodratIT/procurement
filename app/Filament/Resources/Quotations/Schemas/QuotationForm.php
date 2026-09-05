<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\PurchaseRequest;
use App\Services\AttachmentService;
use App\Support\ProcurementPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase request')
                    ->schema([
                        Select::make('purchase_request_id')
                            ->label('PR')
                            ->options(fn (): array => PurchaseRequest::query()
                                ->acrossOffices(ProcurementPermissions::UPDATE)
                                ->whereIn('status', [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW])
                                ->orderByDesc('updated_at')
                                ->get(['id', 'pr_number', 'title'])
                                ->mapWithKeys(fn (PurchaseRequest $request): array => [$request->id => $request->pr_number.' — '.($request->title ?: 'Tanpa judul')])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quotation_number')->label('Nomor quotation')->required()->maxLength(100),
                        TextInput::make('currency')->default('IDR')->required()->length(3)->dehydrateStateUsing(fn (?string $state): string => strtoupper((string) $state)),
                        DatePicker::make('quoted_at')->label('Tanggal penawaran'),
                        DatePicker::make('valid_until')->label('Berlaku sampai'),
                        TextInput::make('discount_amount')->label('Diskon')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('tax_amount')->label('Pajak')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('shipping_amount')->label('Pengiriman')->numeric()->minValue(0)->default(0)->required(),
                        Textarea::make('notes')->label('Catatan vendor')->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Harga per item PR')
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('purchase_request_item_id')
                                    ->label('Item PR')
                                    ->options(fn (Get $get): array => self::requestItemOptions($get->integer('../../purchase_request_id', isNullable: true) ?? $get->integer('purchase_request_id', isNullable: true)))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                                TextInput::make('unit_price')->label('Harga satuan')->numeric()->minValue(0)->required(),
                                Textarea::make('description')->label('Deskripsi')->columnSpan(2),
                                Textarea::make('notes')->label('Catatan item')->columnSpan(2),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                FileUpload::make('attachments')
                    ->label('Lampiran quotation / bukti')
                    ->multiple()
                    ->storeFiles(false)
                    ->visibility('private')
                    ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                    ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024))
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<int|string, string> */
    private static function requestItemOptions(mixed $requestId): array
    {
        if (! is_numeric($requestId)) {
            return [];
        }

        return PurchaseRequest::query()
            ->acrossOffices(ProcurementPermissions::UPDATE)
            ->find((int) $requestId)?->items
            ->mapWithKeys(fn ($item): array => [$item->id => $item->item_name.' × '.$item->quantity])
            ->all() ?? [];
    }
}
