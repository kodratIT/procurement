<?php

namespace App\Filament\Resources;

use App\Filament\Exports\VendorExporter;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Vendor';

    protected static ?string $modelLabel = 'vendor';

    protected static ?string $pluralModelLabel = 'vendor';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('vendor_type')
                    ->options(Vendor::TYPES)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                TextInput::make('contact_name')
                    ->label('Nama kontak')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->maxLength(50),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('tax_number')
                    ->label('Nomor pajak')
                    ->maxLength(50),
                Textarea::make('address')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->relationship()
                    ->label('Item yang disuplai')
                    ->schema([
                        Select::make('item_id')
                            ->relationship(
                                'item',
                                'name',
                                fn (Builder $query): Builder => $query->availableForNewTransactions(),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct(),
                        TextInput::make('reference_price')
                            ->label('Harga referensi')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Select::make('currency')
                            ->options(['IDR' => 'IDR', 'USD' => 'USD', 'SAR' => 'SAR', 'MYR' => 'MYR'])
                            ->default('IDR')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor_type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state): string => Vendor::TYPES[$state] ?? $state),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Item'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (Vendor $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (Vendor $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (Vendor $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (Vendor $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(VendorExporter::class),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVendors::route('/')];
    }
}
