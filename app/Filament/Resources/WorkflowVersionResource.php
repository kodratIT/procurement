<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowVersionResource\Pages\CreateWorkflowVersion;
use App\Filament\Resources\WorkflowVersionResource\Pages\EditWorkflowVersion;
use App\Filament\Resources\WorkflowVersionResource\Pages\ListWorkflowVersions;
use App\Filament\Resources\WorkflowVersionResource\Pages\ViewWorkflowVersion;
use App\Filament\Resources\WorkflowVersionResource\RelationManagers\StepsRelationManager;
use App\Filament\Resources\WorkflowVersionResource\Schemas\WorkflowVersionForm;
use App\Filament\Resources\WorkflowVersionResource\Schemas\WorkflowVersionInfolist;
use App\Filament\Resources\WorkflowVersionResource\Tables\WorkflowVersionsTable;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkflowVersionResource extends Resource
{
    protected static ?string $model = WorkflowVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Workflow Versions';

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'versi workflow';

    public static function form(Schema $schema): Schema
    {
        return WorkflowVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkflowVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:WorkflowVersion'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:WorkflowVersion'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowVersions::route('/'),
            'create' => CreateWorkflowVersion::route('/create'),
            'view' => ViewWorkflowVersion::route('/{record}'),
            'edit' => EditWorkflowVersion::route('/{record}/edit'),
        ];
    }
}
