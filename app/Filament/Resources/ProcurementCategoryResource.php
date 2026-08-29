<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementCategoryExporter;
use App\Models\ProcurementCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcurementCategoryResource extends Resource
{
    protected static ?string $model = ProcurementCategory::class;

    protected static ?string $navigationLabel = 'Kategori';

    protected static ?string $modelLabel = 'kategori';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('type')
                ->options(ProcurementCategory::TYPES)
                ->default(ProcurementCategory::TYPE_GOODS)
                ->required(),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('requires_batch')->label('Wajib Batch Keberangkatan'),
            Toggle::make('requires_vendor')->label('Wajib Vendor'),
            Toggle::make('receiving')->label('Pakai Penerimaan Barang'),
            Toggle::make('invoice')->label('Pakai Invoice'),
            Toggle::make('jamaah')->label('Terkait Jamaah'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => ProcurementCategory::TYPES[$state] ?? $state),
            IconColumn::make('requires_batch')->boolean()->label('Batch'),
            IconColumn::make('requires_vendor')->boolean()->label('Vendor'),
            IconColumn::make('receiving')->boolean()->label('Receiving'),
            IconColumn::make('invoice')->boolean()->label('Invoice'),
            IconColumn::make('jamaah')->boolean()->label('Jamaah'),
            IconColumn::make('is_active')->boolean(),
            TextColumn::make('items_count')->counts('items')->label('Items'),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([ExportBulkAction::make()->exporter(ProcurementCategoryExporter::class), DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementCategories::route('/')];
    }
}
