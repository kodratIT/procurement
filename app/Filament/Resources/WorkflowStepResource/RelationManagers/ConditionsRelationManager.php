<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\RelationManagers;

use App\Enums\WorkflowConditionOperator;
use App\Models\WorkflowCondition;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conditions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('field_key')->required()->maxLength(100),
            Select::make('operator')->options(array_combine(
                array_map(static fn (WorkflowConditionOperator $operator): string => $operator->value, WorkflowConditionOperator::cases()),
                array_map(static fn (WorkflowConditionOperator $operator): string => ucfirst(str_replace('_', ' ', $operator->value)), WorkflowConditionOperator::cases()),
            ))->enum(WorkflowConditionOperator::class)->required(),
            Textarea::make('value')->json()->required()->rules([
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! is_array(json_decode($value, true))) {
                        $fail('The condition value must be a JSON array.');
                    }
                },
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('field_key')->searchable(),
            TextColumn::make('operator')->badge(),
            TextColumn::make('value')->formatStateUsing(static fn (array $state): string => json_encode($state, JSON_THROW_ON_ERROR)),
        ])->headerActions([CreateAction::make()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (WorkflowCondition $record): bool => ! $record->workflowStep->workflowVersion->isUsed()),
        ]);
    }
}
