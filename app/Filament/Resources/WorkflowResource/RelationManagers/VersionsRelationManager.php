<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\RelationManagers;

use App\Enums\WorkflowVersionStatus;
use App\Models\WorkflowVersion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version_number')->numeric()->required()->minValue(1),
            Select::make('status')->options(WorkflowVersionStatus::options())->enum(WorkflowVersionStatus::class)->required()->default(WorkflowVersionStatus::Draft->value),
            DateTimePicker::make('effective_from'),
            DateTimePicker::make('effective_until'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version_number')->label('Versi')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('steps_count')->counts('steps')->label('Tahap'),
            TextColumn::make('effective_from')->dateTime(),
        ])->headerActions([CreateAction::make()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (WorkflowVersion $record): bool => ! $record->isUsed()),
            Action::make('activate')->label('Aktifkan')->visible(fn (WorkflowVersion $record): bool => $record->status !== WorkflowVersionStatus::Active)->action(fn (WorkflowVersion $record): bool => $record->activate()),
            Action::make('retire')->label('Pensiunkan')->visible(fn (WorkflowVersion $record): bool => $record->status === WorkflowVersionStatus::Active)->action(fn (WorkflowVersion $record): bool => $record->retire()),
        ]);
    }
}
