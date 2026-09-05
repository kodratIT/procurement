<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowStepResource\Pages\CreateWorkflowStep;
use App\Filament\Resources\WorkflowStepResource\Pages\EditWorkflowStep;
use App\Filament\Resources\WorkflowStepResource\Pages\ListWorkflowSteps;
use App\Filament\Resources\WorkflowStepResource\Pages\ViewWorkflowStep;
use App\Filament\Resources\WorkflowStepResource\RelationManagers\ConditionsRelationManager;
use App\Filament\Resources\WorkflowStepResource\Schemas\WorkflowStepForm;
use App\Filament\Resources\WorkflowStepResource\Schemas\WorkflowStepInfolist;
use App\Filament\Resources\WorkflowStepResource\Tables\WorkflowStepsTable;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkflowStepResource extends Resource
{
    protected static ?string $model = WorkflowStep::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Workflow Stages';

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'tahap workflow';

    public static function form(Schema $schema): Schema
    {
        return WorkflowStepForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkflowStepInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowStepsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ConditionsRelationManager::class];
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:WorkflowStep'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:WorkflowStep'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowSteps::route('/'),
            'create' => CreateWorkflowStep::route('/create'),
            'view' => ViewWorkflowStep::route('/{record}'),
            'edit' => EditWorkflowStep::route('/{record}/edit'),
        ];
    }
}
