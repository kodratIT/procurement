<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApproverDelegationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delegator.name')->label('Original approver')->searchable()->sortable(),
                TextColumn::make('delegate.name')->label('Delegate')->searchable()->sortable(),
                TextColumn::make('valid_from')->date()->sortable(),
                TextColumn::make('valid_until')->date()->sortable(),
                TextColumn::make('reason')->limit(50)->wrap(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('delegator')->relationship('delegator', 'name')->searchable()->preload(),
                SelectFilter::make('delegate')->relationship('delegate', 'name')->searchable()->preload(),
                SelectFilter::make('is_active')->options(['1' => 'Active', '0' => 'Inactive']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
