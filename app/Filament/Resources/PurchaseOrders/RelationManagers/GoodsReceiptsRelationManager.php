<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class GoodsReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'goodsReceipts';

    protected static ?string $title = 'Goods receipts';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('received_date')->label('Received')->date()->sortable(),
                TextColumn::make('receiver.name')->label('Receiver')->searchable(),
                TextColumn::make('status')->label('Cumulative status')->badge(),
                TextColumn::make('items_count')->counts('items')->label('Lines'),
                TextColumn::make('correction_of_id')->label('Correction')->placeholder('-'),
            ])
            ->headerActions([
                Action::make('recordReceipt')
                    ->label('Record receipt')
                    ->authorize('create')
                    ->schema($this->receiptSchemaComponents())
                    ->action(function (array $data): GoodsReceipt {
                        return app(ReceivingService::class)->record(
                            $this->getOwnerRecord(),
                            $this->serviceData($data),
                            auth()->user(),
                        );
                    }),
            ])
            ->recordActions([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /** @return array<int, Component> */
    private function receiptSchemaComponents(): array
    {
        return [
            DatePicker::make('received_date')->label('Receipt date')->required()->default(now()),
            Select::make('receiver_id')
                ->label('Receiver')
                ->options(fn (): array => $this->receiverOptions())
                ->searchable()
                ->preload()
                ->required()
                ->default(auth()->id()),
            Repeater::make('lines')
                ->label('Received lines')
                ->schema([
                    Select::make('purchase_order_item_id')
                        ->label('PO line')
                        ->options(fn (): array => $this->lineOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                ])
                ->minItems(1)
                ->required()
                ->columns(2),
            Repeater::make('evidence')
                ->label('Delivery evidence')
                ->schema([
                    Select::make('type')->options([
                        'photo' => 'Photo',
                        'surat_jalan' => 'Surat jalan',
                    ])->required(),
                    FileUpload::make('file')
                        ->required()
                        ->storeFiles(false)
                        ->visibility('private')
                        ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                        ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', 10 * 1024 * 1024) / 1024)),
                    TextInput::make('document_number')->label('Document number'),
                    TextInput::make('carrier')->label('Carrier'),
                    TextInput::make('notes')->label('Evidence notes'),
                ])
                ->columns(2)
                ->defaultItems(0),
            TextInput::make('notes')->label('Receipt notes')->maxLength(10000),
        ];
    }

    /** @return array<int|string, string> */
    private function lineOptions(): array
    {
        $order = $this->getOwnerRecord();
        if (! $order instanceof PurchaseOrder) {
            return [];
        }

        return $order->items->mapWithKeys(fn ($item): array => [
            $item->getKey() => sprintf('%s — ordered %s %s', $item->item_name, $item->quantity, $item->unit_name),
        ])->all();
    }

    /** @return array<int|string, string> */
    private function receiverOptions(): array
    {
        $order = $this->getOwnerRecord();
        if (! $order instanceof PurchaseOrder) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('assignments', fn (Builder $query): Builder => $query
                ->currentlyActive()
                ->where('office_id', $order->office_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, mixed> */
    private function serviceData(array $data): array
    {
        $data['evidence'] = collect($data['evidence'] ?? [])->map(fn (array $evidence): array => [
            'file' => $evidence['file'],
            'type' => $evidence['type'],
            'metadata' => array_filter([
                'document_number' => $evidence['document_number'] ?? null,
                'carrier' => $evidence['carrier'] ?? null,
                'notes' => $evidence['notes'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ])->values()->all();

        return $data;
    }
}
