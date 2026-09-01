<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\RelationManagers;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use App\Models\WorkflowStep;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sequence')->numeric()->required()->minValue(1)->integer(),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('step_type')->options(WorkflowStepType::options())->enum(WorkflowStepType::class)->required(),
            Select::make('approval_mode')->options(WorkflowApprovalMode::options())->enum(WorkflowApprovalMode::class)->required()->default(WorkflowApprovalMode::Sequential->value),
            TextInput::make('resolver_type')->maxLength(50),
            TextInput::make('required_permission')->maxLength(100),
            Toggle::make('is_required')->default(true),
            TextInput::make('sla_minutes')->numeric()->minValue(0)->integer(),
            TextInput::make('escalation_type')->maxLength(30),
            TextInput::make('settings')->json(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('sequence')->columns([
            TextColumn::make('sequence')->sortable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('step_type')->badge(),
            TextColumn::make('approval_mode')->badge(),
            TextColumn::make('conditions_count')->counts('conditions')->label('Kondisi'),
        ])->headerActions([CreateAction::make()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (WorkflowStep $record): bool => ! $record->workflowVersion->isUsed()),
        ]);
    }
}
