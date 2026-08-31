<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Forms\DynamicFieldSchema;
use App\Filament\Resources\PurchaseRequests\Pages\CreatePurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ManagePurchaseRequests;
use App\Models\ProcurementField;
use App\Models\ProcurementItem;
use App\Models\ProcurementVariant;
use App\Models\PurchaseRequest;
use App\Services\AccessContextService;
use App\Services\AttachmentService;
use App\Services\ProcurementRequestSubmitter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Purchase Request';

    protected static ?string $modelLabel = 'purchase request';

    protected static ?string $pluralModelLabel = 'purchase request';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konteks organisasi')
                    ->schema([
                        TextInput::make('requester_id')
                            ->label('Pengaju')
                            ->default(fn (): ?string => auth()->id() === null ? null : (string) auth()->id())
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('office_id')
                            ->label('Kantor aktif')
                            ->default(fn (): ?string => ($id = app(AccessContextService::class)->id()) === null ? null : (string) $id)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('branch_id')
                            ->label('Cabang aktif')
                            ->default(fn (): ?string => ($id = app(AccessContextService::class)->branch()?->getKey()) === null ? null : (string) $id)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('department_id')
                            ->label('Departemen aktif')
                            ->default(fn (): ?string => ($id = app(AccessContextService::class)->department()?->getKey()) === null ? null : (string) $id)
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('cost_center_id')
                            ->label('Cost center')
                            ->relationship('costCenter', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('departure_batch_id')
                            ->label('Batch keberangkatan')
                            ->relationship('departureBatch', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),
                Section::make('Detail draft')
                    ->schema([
                        TextInput::make('title')->label('Judul')->maxLength(255),
                        DatePicker::make('required_date')->label('Tanggal kebutuhan'),
                        Select::make('priority')
                            ->options(array_combine(PurchaseRequest::DRAFT_PRIORITIES, ['Rendah', 'Normal', 'Tinggi', 'Mendesak']))
                            ->default('normal')
                            ->required(),
                        Textarea::make('reason')->label('Alasan/kebutuhan')->required()->columnSpanFull(),
                        Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Item yang diminta')
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('procurement_item_id')
                                    ->label('Item')
                                    ->relationship('procurementItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('procurement_unit_id')
                                    ->label('Satuan')
                                    ->options(fn (Get $get): array => self::unitOptions($get->integer('procurement_item_id')))
                                    ->disabled(fn (Get $get): bool => ! filled($get->get('procurement_item_id'))),
                                Select::make('procurement_variant_id')
                                    ->label('Varian')
                                    ->options(fn (Get $get): array => self::variantOptions($get->integer('procurement_item_id')))
                                    ->disabled(fn (Get $get): bool => ! filled($get->get('procurement_item_id'))),
                                TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                                TextInput::make('unit_price')->label('Estimasi harga satuan')->numeric()->minValue(0)->required(),
                                TextInput::make('item_name')->label('Nama bebas')->maxLength(255),
                                Textarea::make('description')->label('Deskripsi')->columnSpan(2),
                                KeyValue::make('specifications')->label('Spesifikasi')->columnSpan(2),
                                Textarea::make('notes')->label('Catatan item')->columnSpan(2),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                Section::make('Field kategori')
                    ->schema(fn (Get $get): array => self::dynamicFieldComponents($get->integer('category_id')))
                    ->columns(2)
                    ->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label('Lampiran')
                    ->multiple()
                    ->storeFiles(false)
                    ->visibility('private')
                    ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                    ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pr_number')->label('Nomor PR')->searchable()->sortable(),
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('requester.name')->label('Pengaju'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_amount')->label('Total')->sortable(),
                TextColumn::make('priority')->label('Prioritas'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('priority')->options(array_combine(PurchaseRequest::DRAFT_PRIORITIES, ['Rendah', 'Normal', 'Tinggi', 'Mendesak'])),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')
                    ->label('Submit')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseRequest $record): bool => $record->isCorrectable())
                    ->authorize('submit')
                    ->action(fn (PurchaseRequest $record): PurchaseRequest => app(ProcurementRequestSubmitter::class)->submit($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('status', [
            PurchaseRequest::STATUS_DRAFT,
            PurchaseRequest::STATUS_RETURNED,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchaseRequests::route('/'),
            'create' => CreatePurchaseRequest::route('/create'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function dynamicFieldComponents(
        ?int $categoryId,
        string $stage = ProcurementField::EDITABLE_STAGE_DRAFT,
    ): array {
        return app(DynamicFieldSchema::class)->components($categoryId, $stage);
    }

    /** @return array<int|string, string> */
    private static function unitOptions(?int $itemId): array
    {
        if ($itemId === null) {
            return [];
        }

        $item = ProcurementItem::query()->with('unit')->find($itemId);

        return $item?->unit === null ? [] : [$item->unit->getKey() => $item->unit->name];
    }

    /** @return array<int|string, string> */
    private static function variantOptions(?int $itemId): array
    {
        if ($itemId === null) {
            return [];
        }

        return ProcurementVariant::query()
            ->where('item_id', $itemId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
