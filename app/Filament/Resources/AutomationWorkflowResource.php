<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Packstub\Flow\Filament\Resources\WorkflowResource as BaseWorkflowResource;
use UnitEnum;

class AutomationWorkflowResource extends BaseWorkflowResource
{
    protected static ?string $slug = 'automations';

    protected static ?string $navigationLabel = 'Automations';

    protected static string|\UnitEnum|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'automation';

    protected static ?string $pluralModelLabel = 'Automations';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedBolt;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Automation';
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(static::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Workflow'));
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }
}
