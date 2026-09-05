<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\FeatureModuleService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class FeatureModules extends Page
{
    use HasPageShield {
        canAccess as protected hasPageShieldCanAccess;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Feature Modules';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Feature Modules';

    protected string $view = 'filament.pages.feature-modules';

    public static function canAccess(): bool
    {
        // HasPageShield checks View:FeatureModules via FilamentShield::getPages().
        // Combine with FeatureModuleService::canManage() for backward compatibility
        // during procurement.* -> Shield migration (RolePermissionSeeder still grants
        // procurement.manage-features). Once Shield grants View:FeatureModules,
        // the legacy check can be removed or changed to && for strict enforcement.
        return self::hasPageShieldCanAccess() || app(FeatureModuleService::class)->canManage();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Feature Modules';
    }

    /** @return list<array<string, mixed>> */
    public function sections(): array
    {
        return app(FeatureModuleService::class)->navigationSections();
    }

    public function toggleSection(string $key): void
    {
        $service = app(FeatureModuleService::class);
        $enabled = ! $service->isOwnStateEnabled($key);
        $service->toggleSection($key, $enabled, $this->actor());

        Notification::make()
            ->title($enabled ? 'Section diaktifkan' : 'Section dinonaktifkan')
            ->success()
            ->send();
    }

    public function toggleFeature(string $key): void
    {
        $service = app(FeatureModuleService::class);
        $enabled = ! $service->isOwnStateEnabled($key);
        $service->toggleFeature($key, $enabled, $this->actor());

        Notification::make()
            ->title($enabled ? 'Fitur diaktifkan' : 'Fitur dinonaktifkan')
            ->success()
            ->send();
    }

    private function actor(): User
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new InvalidArgumentException('An authenticated user is required.');
        }

        return $actor;
    }
}
