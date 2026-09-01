<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\WorkflowVersionStatus;
use App\Filament\Resources\WorkflowVersionResource\RelationManagers\StepsRelationManager;
use App\Models\WorkflowVersion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowVersionResource extends Resource
{
    protected static ?string $model = WorkflowVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Versi Workflow';

    protected static ?string $modelLabel = 'versi workflow';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('workflow_id')->relationship('workflow', 'name')->required(),
            TextInput::make('version_number')->numeric()->required()->minValue(1),
            Select::make('status')->options(WorkflowVersionStatus::options())->enum(WorkflowVersionStatus::class)->required()->default(WorkflowVersionStatus::Draft->value),
            DateTimePicker::make('effective_from'),
            DateTimePicker::make('effective_until')->after('effective_from'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('workflow.name')->label('Workflow')->searchable()->sortable(),
            TextColumn::make('version_number')->label('Versi')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('steps_count')->counts('steps')->label('Tahap'),
            TextColumn::make('effective_from')->dateTime(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (WorkflowVersion $record): bool => ! $record->isUsed()),
        ]);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkflowVersions::route('/'),
        ];
    }
}
