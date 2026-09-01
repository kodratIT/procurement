<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use App\Filament\Resources\WorkflowStepResource\RelationManagers\ConditionsRelationManager;
use App\Models\WorkflowStep;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowStepResource extends Resource
{
    protected static ?string $model = WorkflowStep::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Tahap Workflow';

    protected static ?string $modelLabel = 'tahap workflow';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('workflow_version_id')->relationship('workflowVersion', 'id')->required(),
            TextInput::make('sequence')->numeric()->required()->minValue(1),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('step_type')->options(WorkflowStepType::options())->enum(WorkflowStepType::class)->required(),
            Select::make('approval_mode')->options(WorkflowApprovalMode::options())->enum(WorkflowApprovalMode::class)->required()->default(WorkflowApprovalMode::Sequential->value),
            TextInput::make('resolver_type')->maxLength(50),
            TextInput::make('required_permission')->maxLength(100),
            Toggle::make('is_required')->default(true),
            TextInput::make('sla_minutes')->numeric()->minValue(0),
            TextInput::make('escalation_type')->maxLength(30),
            TextInput::make('settings')->json(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('workflowVersion.workflow.name')->label('Workflow')->searchable(),
            TextColumn::make('workflowVersion.version_number')->label('Versi'),
            TextColumn::make('sequence')->sortable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('step_type')->badge(),
            TextColumn::make('conditions_count')->counts('conditions')->label('Kondisi'),
            IconColumn::make('is_required')->boolean()->label('Wajib'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ConditionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkflowSteps::route('/'),
        ];
    }
}
