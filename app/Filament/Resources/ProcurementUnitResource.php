<?php
namespace App\Filament\Resources;
use App\Models\ProcurementUnit;
use Filament\Actions\{DeleteAction,DeleteBulkAction,EditAction};
use Filament\Forms\Components\{TextInput,Toggle}; use Filament\Resources\Resource; use Filament\Schemas\Schema; use Filament\Tables\Columns\{IconColumn,TextColumn}; use Filament\Tables\Table;
class ProcurementUnitResource extends Resource { protected static ?string $model=ProcurementUnit::class; protected static ?string $navigationLabel='Satuan'; public static function form(Schema $s):Schema{return $s->components([TextInput::make('code')->required()->unique(ignoreRecord:true),TextInput::make('name')->required(),TextInput::make('symbol'),Toggle::make('is_active')->default(true)]);} public static function table(Table $t):Table{return $t->columns([TextColumn::make('code')->searchable(),TextColumn::make('name')->searchable(),TextColumn::make('symbol'),IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make(),DeleteAction::make()])->toolbarActions([DeleteBulkAction::make()]);} public static function getPages():array{return ['index'=>Pages\ManageProcurementUnits::route('/')];}}
