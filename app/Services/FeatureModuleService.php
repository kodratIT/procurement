<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class FeatureModuleService
{
    public const CACHE_KEY = 'procurement.feature-flags.v1';

    public function __construct(
        private readonly FeatureRegistry $registry,
        private readonly AccessContextService $context,
        private readonly AuthorizationService $authorization,
    ) {}

    public function isEnabled(string $key): bool
    {
        if ($this->registry->isCore($key)) {
            return true;
        }

        if ($this->registry->section($key) !== null) {
            return $this->stateMap()[$key] ?? true;
        }

        $feature = $this->registry->feature($key);
        if ($feature === null) {
            return false;
        }

        $states = $this->stateMap();

        return ($states[$feature['section_key']] ?? true) && ($states[$key] ?? true);
    }

    public function isOwnStateEnabled(string $key): bool
    {
        if ($this->registry->isCore($key)) {
            return true;
        }

        if (! $this->registry->isManagedKey($key)) {
            return false;
        }

        return $this->stateMap()[$key] ?? true;
    }

    /** @return list<array<string, mixed>> */
    public function navigationSections(): array
    {
        $states = $this->stateMap();
        $sections = [];

        foreach ($this->registry->sections() as $section) {
            $sectionEnabled = $states[$section['key']] ?? true;
            $features = [];

            foreach ($section['feature_keys'] as $featureKey) {
                $feature = $this->registry->feature($featureKey);
                if ($feature === null) {
                    continue;
                }

                $featureEnabled = $states[$featureKey] ?? true;
                $features[] = array_merge($feature, [
                    'enabled' => $featureEnabled,
                    'effective' => $sectionEnabled && $featureEnabled,
                    'status' => ! $sectionEnabled
                        ? 'Nonaktif karena section'
                        : ($featureEnabled ? 'Aktif' : 'Nonaktif'),
                ]);
            }

            $sections[] = array_merge($section, [
                'enabled' => $sectionEnabled,
                'effective' => $sectionEnabled,
                'feature_count' => count($features),
                'features' => $features,
            ]);
        }

        return $sections;
    }

    public function canManage(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $this->hasValidPanelContext($user)) {
            return false;
        }

        return $this->isSuperAdmin($user)
            || $this->authorization->allows($user, ProcurementPermissions::MANAGE_FEATURES);
    }

    public function assertCanManage(?User $user = null): User
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $this->canManage($user)) {
            throw new AuthorizationException('You are not authorized to manage feature modules.');
        }

        return $user;
    }

    /** @param callable(User): bool|null $existingAuthorization */
    public function allowsResource(string $resource, ?callable $existingAuthorization = null): bool
    {
        $user = Auth::user();
        $featureKey = $this->registry->featureForResource($resource);

        if (! $user instanceof User || $featureKey === null || ! $this->hasValidPanelContext($user)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $this->isEnabled($featureKey)) {
            return false;
        }

        return $existingAuthorization === null || $existingAuthorization($user);
    }

    public function assertResource(string $resource, ?User $actor = null): string
    {
        $featureKey = $this->registry->featureForResource($resource);
        if ($featureKey === null) {
            throw new InvalidArgumentException("No feature is registered for resource {$resource}.");
        }

        $actor ??= Auth::user();
        if ($actor instanceof User && $this->isSuperAdmin($actor) && $this->hasValidPanelContext($actor)) {
            return $featureKey;
        }

        $this->assertEnabled($featureKey, $actor);

        return $featureKey;
    }

    public function featureIsAvailable(string $key, ?User $actor = null): bool
    {
        if ($this->registry->isCore($key)) {
            return true;
        }

        if (! $this->registry->isManagedKey($key)) {
            return false;
        }

        $actor ??= Auth::user();

        return $actor instanceof User
            && $this->isSuperAdmin($actor)
            && $this->hasValidPanelContext($actor)
            ? true
            : $this->isEnabled($key);
    }

    public function assertEnabled(string $key, ?User $actor = null): void
    {
        if (! $this->registry->isCore($key) && ! $this->registry->isManagedKey($key)) {
            throw new InvalidArgumentException("Unknown feature key {$key}.");
        }

        if (! $this->featureIsAvailable($key, $actor)) {
            throw new AuthorizationException('Fitur sedang dinonaktifkan administrator.');
        }
    }

    public function featureForSubject(mixed $subject, ?string $explicitOwner = null): ?string
    {
        return $this->registry->featureForSubject($subject, $explicitOwner);
    }

    public function toggleSection(string $key, bool $enabled, User $actor): void
    {
        $this->assertCanManage($actor);
        $section = $this->registry->section($key);

        if ($section === null) {
            throw new InvalidArgumentException("Unknown feature section {$key}.");
        }

        $this->mutate($key, $enabled, $section['key'], $actor, $section['feature_keys']);
    }

    public function toggleFeature(string $key, bool $enabled, User $actor): void
    {
        $this->assertCanManage($actor);
        $feature = $this->registry->feature($key);

        if ($feature === null) {
            throw new InvalidArgumentException("Unknown feature {$key}.");
        }

        $this->mutate($key, $enabled, $feature['section_key'], $actor, [$key]);
    }

    private function mutate(string $key, bool $enabled, string $sectionKey, User $actor, array $affectedFeatureKeys): void
    {
        $this->registry->validate();

        $changed = DB::transaction(function () use ($key, $enabled, $sectionKey, $actor, $affectedFeatureKeys): bool {
            $this->ensureStateRow($sectionKey);
            $section = FeatureFlag::query()
                ->where('key', $sectionKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($key !== $sectionKey && ! (bool) $section->enabled) {
                throw new AuthorizationException('Enable the section before changing one of its features.');
            }

            $this->ensureStateRow($key);
            $target = $key === $sectionKey
                ? $section
                : FeatureFlag::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $oldEnabled = (bool) $target->enabled;

            if ($oldEnabled === $enabled) {
                return false;
            }
            if (! config('activitylog.enabled', true)) {
                throw new RuntimeException('Feature changes require activity logging to be enabled.');
            }

            $target->forceFill([
                'enabled' => $enabled,
                'updated_by' => $actor->getKey(),
            ])->save();

            activity('feature-modules')
                ->performedOn($target)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'feature_key' => $key,
                    'scope' => $key === $sectionKey ? 'section' : 'feature',
                    'section_key' => $sectionKey,
                    'old_enabled' => $oldEnabled,
                    'new_enabled' => $enabled,
                    'affected_feature_keys' => array_values($affectedFeatureKeys),
                ])
                ->log('Feature module updated');

            return true;
        });

        if ($changed) {
            DB::afterCommit(fn (): bool => Cache::forget(self::CACHE_KEY));
        }
    }

    private function ensureStateRow(string $key): void
    {
        FeatureFlag::query()->insertOrIgnore([
            'key' => $key,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, bool> */
    private function stateMap(): array
    {
        if (! Schema::hasTable('feature_flags')) {
            return [];
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(5),
            fn (): array => FeatureFlag::query()
                ->pluck('enabled', 'key')
                ->map(static fn (mixed $enabled): bool => (bool) $enabled)
                ->all(),
        );
    }

    private function hasValidPanelContext(User $user): bool
    {
        if (! $user->is_active || Auth::id() !== $user->getKey()) {
            return false;
        }

        $assignment = $this->context->assignment();

        return $assignment instanceof UserAssignment
            && $assignment->user_id === $user->getKey()
            && $assignment->isCurrentlyActive()
            && $assignment->office !== null;
    }

    public function isSuperAdminForAuthorization(User $user): bool
    {
        return $this->isSuperAdmin($user) && $this->hasValidPanelContext($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole((string) config('filament-shield.super_admin.name', 'super_admin'), 'web');
    }
}
