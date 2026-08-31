<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bindings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('transaction_type')->maxLength(50),
            TextInput::make('office_id')->numeric(),
            TextInput::make('branch_id')->numeric(),
            TextInput::make('department_id')->numeric(),
            TextInput::make('category_id')->numeric(),
            TextInput::make('cost_center_id')->numeric(),
            TextInput::make('minimum_amount')->numeric()->minValue(0),
            TextInput::make('maximum_amount')->numeric()->minValue(0),
            TextInput::make('priority')->numeric()->default(0)->required(),
            Textarea::make('conditions')->json(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('transaction_type')->label('Transaksi')->placeholder('Semua'),
            TextColumn::make('priority')->sortable(),
            TextColumn::make('minimum_amount')->numeric(),
            TextColumn::make('maximum_amount')->numeric(),
            IconColumn::make('is_active')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
