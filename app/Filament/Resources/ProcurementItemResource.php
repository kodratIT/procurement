<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementItemExporter;
use App\Models\ProcurementItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementItemResource extends Resource
{
    protected static ?string $model = ProcurementItem::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Item';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'item';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('SKU')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('category_id')
                    ->relationship('category', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('unit_id')
                    ->relationship('unit', 'name', fn (Builder $query): Builder => $query->active())
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('reference_price')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp'),
                Select::make('reference_currency')
                    ->options(['IDR' => 'IDR', 'USD' => 'USD', 'SAR' => 'SAR', 'MYR' => 'MYR'])
                    ->default('IDR')
                    ->required(),
                KeyValue::make('specifications')
                    ->label('Spesifikasi')
                    ->keyLabel('Atribut')
                    ->valueLabel('Nilai')
                    ->addActionLabel('Tambah spesifikasi')
                    ->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Kategori')->searchable()->sortable(),
                TextColumn::make('unit.name')->label('Satuan')->sortable(),
                TextColumn::make('reference_price')
                    ->label('Harga Ref')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '-' : number_format((float) $state, 0, ',', '.')),
                TextColumn::make('reference_currency')->label('Mata Uang')->toggleable(),
                TextColumn::make('specifications')->label('Spesifikasi')->limit(30)->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('variants_count')->counts('variants')->label('Varian'),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('unit')->relationship('unit', 'name')->searchable()->preload(),
                SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (ProcurementItem $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (ProcurementItem $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (ProcurementItem $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (ProcurementItem $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(ProcurementItemExporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementItems::route('/')];
    }
}
