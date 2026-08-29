<?php

namespace App\Filament\Resources;

use App\Filament\Exports\VendorExporter;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
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

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('name')->required(), TextInput::make('contact_name')->label('Kontak'), TextInput::make('phone'), TextInput::make('email')->email(), Textarea::make('address')->columnSpanFull(), Toggle::make('is_active')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('code')->searchable(), TextColumn::make('name')->searchable(), TextColumn::make('contact_name'), TextColumn::make('phone'), TextColumn::make('email'), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([ExportBulkAction::make()->exporter(VendorExporter::class), DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVendors::route('/')];
    }
}
