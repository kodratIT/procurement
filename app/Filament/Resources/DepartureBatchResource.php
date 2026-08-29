<?php

namespace App\Filament\Resources;

use App\Models\DepartureBatch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartureBatchResource extends Resource
{
    protected static ?string $model = DepartureBatch::class;

    protected static ?string $navigationLabel = 'Batch Keberangkatan';

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('name')->required(), DatePicker::make('departure_date')->required(), DatePicker::make('return_date')->afterOrEqual('departure_date'), TextInput::make('capacity')->numeric()->minValue(1), Select::make('status')->options(['planned' => 'Planned', 'open' => 'Open', 'closed' => 'Closed', 'departed' => 'Departed'])->default('planned')->required(), Toggle::make('is_active')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('code')->searchable(), TextColumn::make('name')->searchable(), TextColumn::make('departure_date')->date()->sortable(), TextColumn::make('return_date')->date(), TextColumn::make('status')->badge(), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDepartureBatches::route('/')];
    }
}
