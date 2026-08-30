<?php

namespace App\Filament\Resources\Offices;

use App\Filament\Resources\Offices\Pages\ManageOffices;
use App\Models\Office;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Kantor';

    protected static ?string $modelLabel = 'kantor';

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
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('is_active')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (Office $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(function (Office $record): void {
                        $record->deactivate();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOffices::route('/'),
        ];
    }
}
