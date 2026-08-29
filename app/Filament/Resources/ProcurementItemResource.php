<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementItemExporter;
use App\Models\ProcurementItem;
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

class ProcurementItemResource extends Resource
{
    protected static ?string $model = ProcurementItem::class;

    protected static ?string $navigationLabel = 'Item';

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('name')->required(), Select::make('category_id')->relationship('category', 'name')->searchable()->preload()->required(), Select::make('unit_id')->relationship('unit', 'name')->searchable()->preload()->required(), Textarea::make('description')->columnSpanFull(), Toggle::make('is_active')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('code')->searchable(), TextColumn::make('name')->searchable(), TextColumn::make('category.name')->label('Kategori'), TextColumn::make('unit.name')->label('Satuan'), IconColumn::make('is_active')->boolean(), TextColumn::make('variants_count')->counts('variants')->label('Varian')])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([ExportBulkAction::make()->exporter(ProcurementItemExporter::class), DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementItems::route('/')];
    }
}
