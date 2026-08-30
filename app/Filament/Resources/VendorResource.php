<?php

namespace App\Filament\Resources;

use App\Filament\Exports\VendorExporter;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationLabel = 'Vendor';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            Select::make('status')->options(Vendor::STATUSES)->required()->default(Vendor::STATUS_ACTIVE),
            Toggle::make('is_active')->default(true),
            TextInput::make('contact_name')->label('Kontak Utama'),
            TextInput::make('phone'),
            TextInput::make('email')->email(),
            Textarea::make('address')->columnSpanFull(),
            Textarea::make('status_note')->label('Catatan Status')->columnSpanFull(),
            Repeater::make('contacts')
                ->relationship()
                ->label('Daftar Kontak')
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('position')->label('Jabatan'),
                    TextInput::make('phone'),
                    TextInput::make('email')->email(),
                    TextInput::make('whatsapp'),
                    Toggle::make('is_primary')->label('Kontak Utama'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Repeater::make('bankAccounts')
                ->relationship()
                ->label('Rekening Bank')
                ->schema([
                    TextInput::make('bank_name')->required(),
                    TextInput::make('account_name')->required(),
                    TextInput::make('account_number')->required(),
                    TextInput::make('currency')->default('IDR')->required(),
                    TextInput::make('branch'),
                    TextInput::make('swift_code'),
                    Toggle::make('is_primary')->label('Rekening Utama'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Repeater::make('taxes')
                ->relationship()
                ->label('Data Pajak')
                ->schema([
                    TextInput::make('tax_number')->label('NPWP/Nomor Pajak'),
                    TextInput::make('tax_type')->default('PPN')->required(),
                    TextInput::make('tax_name')->label('Nama Terdaftar'),
                    TextInput::make('address')->columnSpanFull(),
                    DatePicker::make('registered_at'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Repeater::make('items')
                ->relationship()
                ->label('Harga Item Vendor')
                ->schema([
                    Select::make('item_id')->relationship('item', 'name')->searchable()->preload()->required(),
                    TextInput::make('price')->numeric()->prefix('Rp')->required(),
                    TextInput::make('currency')->default('IDR')->required(),
                    TextInput::make('vendor_sku'),
                    TextInput::make('min_order_qty')->numeric()->integer()->default(1)->minValue(1),
                    TextInput::make('lead_time_days')->numeric()->integer()->minValue(0),
                    DatePicker::make('price_valid_from'),
                    DatePicker::make('price_valid_until')->afterOrEqual('price_valid_from'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Repeater::make('documents')
                ->relationship()
                ->label('Dokumen Vendor')
                ->schema([
                    TextInput::make('name')->required(),
                    Select::make('document_type')->options(VendorDocument::TYPES)->default(VendorDocument::TYPE_OTHER)->required(),
                    FileUpload::make('file_path')->disk('public')->directory('vendor-documents')->acceptedFileTypes(['application/pdf', 'image/*'])->storeFileNamesIn('file_name'),
                    DatePicker::make('issued_at'),
                    DatePicker::make('expires_at')->afterOrEqual('issued_at'),
                    Textarea::make('note'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => Vendor::STATUSES[$state] ?? $state),
                TextColumn::make('contact_name'),
                TextColumn::make('phone'),
                TextColumn::make('items_count')->counts('items')->label('Item'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([ExportBulkAction::make()->exporter(VendorExporter::class), DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVendors::route('/')];
    }
}
