<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowResource\Pages\CreateWorkflow;
use App\Filament\Resources\WorkflowResource\Pages\EditWorkflow;
use App\Filament\Resources\WorkflowResource\Pages\ListWorkflows;
use App\Filament\Resources\WorkflowResource\Pages\ViewWorkflow;
use App\Filament\Resources\WorkflowResource\RelationManagers\BindingsRelationManager;
use App\Filament\Resources\WorkflowResource\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\WorkflowResource\Schemas\WorkflowForm;
use App\Filament\Resources\WorkflowResource\Schemas\WorkflowInfolist;
use App\Filament\Resources\WorkflowResource\Tables\WorkflowsTable;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Workflows';

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'workflow';

    public static function form(Schema $schema): Schema
    {
        return WorkflowForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkflowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
            BindingsRelationManager::class,
        ];
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Workflow'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Workflow'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflows::route('/'),
            'create' => CreateWorkflow::route('/create'),
            'view' => ViewWorkflow::route('/{record}'),
            'edit' => EditWorkflow::route('/{record}/edit'),
        ];
    }
}
