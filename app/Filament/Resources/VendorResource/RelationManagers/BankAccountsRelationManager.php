<?php

namespace App\Filament\Resources\VendorResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'bankAccounts';

    protected static ?string $title = 'Rekening Bank';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('bank_name')->label('Nama Bank')->required(),
            TextInput::make('account_name')->label('Atas Nama')->required(),
            TextInput::make('account_number')->label('Nomor Rekening')->required(),
            Select::make('currency')->options(['IDR' => 'IDR', 'USD' => 'USD', 'SAR' => 'SAR'])->default('IDR')->required(),
            TextInput::make('branch')->label('Cabang'),
            TextInput::make('swift_code')->label('Kode SWIFT'),
            Toggle::make('is_primary')->label('Rekening Utama')->default(false),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')->label('Bank')->searchable(),
                TextColumn::make('account_name')->label('Atas Nama')->searchable(),
                TextColumn::make('account_number')->label('No. Rekening')->copyable(),
                TextColumn::make('currency'),
                TextColumn::make('branch')->label('Cabang'),
                IconColumn::make('is_primary')->boolean()->label('Utama'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
