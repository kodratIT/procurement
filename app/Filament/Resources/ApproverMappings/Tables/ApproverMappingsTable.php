<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Tables;

use App\Models\ApproverMapping;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApproverMappingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflowStep.workflowVersion.workflow.name')->label('Workflow')->searchable()->toggleable(),
                TextColumn::make('resolver_type')->label('Resolver')->badge()->searchable(),
                TextColumn::make('role.name')->label('Role')->searchable()->toggleable(),
                TextColumn::make('user.name')->label('User')->searchable()->toggleable(),
                TextColumn::make('office.name')->label('Kantor')->searchable()->sortable(),
                TextColumn::make('branch.name')->label('Cabang')->toggleable(),
                TextColumn::make('department.name')->label('Departemen')->toggleable(),
                TextColumn::make('costCenter.name')->label('Cost center')->toggleable(),
                TextColumn::make('scope_source')->label('Source')->badge(),
                TextColumn::make('fallback_type')->label('Fallback')->badge(),
                IconColumn::make('allow_self_approval')->label('Self approval')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('valid_from')->date()->sortable(),
                TextColumn::make('valid_until')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('resolver_type')->options(array_combine(ApproverMapping::RESOLVER_TYPES, ApproverMapping::RESOLVER_TYPES)),
                SelectFilter::make('office')->relationship('office', 'name')->searchable()->preload(),
                SelectFilter::make('role')->relationship('role', 'name')->searchable()->preload(),
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
