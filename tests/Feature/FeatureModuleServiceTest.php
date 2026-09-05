<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProcurementCategoryResource;
use App\Filament\Resources\ProcurementItemResource;
use App\Filament\Resources\ProcurementReviewResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\FeatureFlag;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ApprovalInstanceCreator;
use App\Services\ApprovalTaskLifecycleService;
use App\Services\BudgetReservationService;
use App\Services\FeatureModuleService;
use App\Services\FeatureRegistry;
use Database\Seeders\FeatureFlagSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class FeatureModuleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget(FeatureModuleService::CACHE_KEY);
        parent::tearDown();
    }

    public function test_registry_hydrates_six_sections_and_thirty_features(): void
    {
        $registry = app(FeatureRegistry::class);

        $registry->validate();

        $this->assertCount(7, $registry->sections());
        $this->assertCount(30, $registry->features());
        $this->assertCount(37, $registry->stateKeys());
        $this->assertCount(37, FeatureFlag::query()->get());
        $this->assertNull($registry->featureForModel(PurchaseRequest::class));
        $this->assertSame(
            FeatureRegistry::FEATURE_REQUESTS,
            $registry->featureForResource(PurchaseRequestResource::class),
        );
        $this->assertSame(
            FeatureRegistry::FEATURE_PROCUREMENT_REVIEWS,
            $registry->featureForResource(ProcurementReviewResource::class),
        );
        $this->assertTrue(app(FeatureModuleService::class)->isEnabled(FeatureRegistry::CORE_DASHBOARD));
        $this->assertTrue(app(FeatureModuleService::class)->isEnabled(FeatureRegistry::CORE_FEATURE_MODULES));
    }

    public function test_feature_toggle_updates_state_audit_and_cache(): void
    {
        $user = $this->authenticatedUser('Admin');
        $service = app(FeatureModuleService::class);
        Cache::put(FeatureModuleService::CACHE_KEY, ['sentinel' => true]);

        $service->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);

        $this->assertDatabaseHas('feature_flags', [
            'key' => FeatureRegistry::FEATURE_ITEMS,
            'enabled' => false,
            'updated_by' => $user->id,
        ]);
        $this->assertFalse(Cache::has(FeatureModuleService::CACHE_KEY));

        $activity = Activity::query()
            ->where('log_name', 'feature-modules')
            ->where('subject_type', FeatureFlag::class)
            ->latest('id')
            ->firstOrFail();

        $properties = $activity->properties->toArray();
        $this->assertSame(FeatureRegistry::FEATURE_ITEMS, $properties['feature_key']);
        $this->assertSame('feature', $properties['scope']);
        $this->assertSame(FeatureRegistry::SECTION_MASTER_DATA, $properties['section_key']);
        $this->assertSame(true, $properties['old_enabled']);
        $this->assertSame(false, $properties['new_enabled']);
        $this->assertSame([FeatureRegistry::FEATURE_ITEMS], $properties['affected_feature_keys']);
    }

    public function test_noop_toggle_preserves_timestamp_audit_and_cached_state(): void
    {
        $user = $this->authenticatedUser('Admin');
        $service = app(FeatureModuleService::class);
        $flag = FeatureFlag::query()->where('key', FeatureRegistry::FEATURE_ITEMS)->firstOrFail();
        $updatedAt = $flag->updated_at?->toISOString();
        Cache::put(FeatureModuleService::CACHE_KEY, ['sentinel' => true]);
        config(['activitylog.enabled' => false]);

        $service->toggleFeature(FeatureRegistry::FEATURE_ITEMS, true, $user);

        $this->assertSame(['sentinel' => true], Cache::get(FeatureModuleService::CACHE_KEY));
        $this->assertSame($updatedAt, $flag->fresh()->updated_at?->toISOString());
        $this->assertSame(0, Activity::query()->where('log_name', 'feature-modules')->count());
    }

    public function test_section_toggle_controls_effective_state_without_losing_child_state(): void
    {
        $user = $this->authenticatedUser('Admin');
        $service = app(FeatureModuleService::class);
        $service->toggleFeature(FeatureRegistry::FEATURE_QUOTES, false, $user);
        $service->toggleSection(FeatureRegistry::SECTION_PROCUREMENT, false, $user);
        $sectionActivity = Activity::query()
            ->where('log_name', 'feature-modules')
            ->latest('id')
            ->firstOrFail();
        $sectionProperties = $sectionActivity->properties->toArray();
        $this->assertSame([
            FeatureRegistry::FEATURE_REQUESTS,
            FeatureRegistry::FEATURE_QUOTES,
            FeatureRegistry::FEATURE_PURCHASE_ORDERS,
            FeatureRegistry::FEATURE_INVOICES,
            FeatureRegistry::FEATURE_DISTRIBUTIONS,
        ], $sectionProperties['affected_feature_keys']);

        $this->assertFalse($service->isEnabled(FeatureRegistry::FEATURE_REQUESTS));
        $procurement = $service->navigationSections()[0];
        $this->assertSame('Nonaktif karena section', $procurement['features'][0]['status']);
        $this->assertFalse($procurement['features'][0]['effective']);
        $this->assertFalse($service->isEnabled(FeatureRegistry::FEATURE_QUOTES));
        $this->assertFalse($service->isOwnStateEnabled(FeatureRegistry::FEATURE_QUOTES));

        $rejected = false;
        try {
            $service->toggleFeature(FeatureRegistry::FEATURE_REQUESTS, true, $user);
        } catch (AuthorizationException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $service->toggleSection(FeatureRegistry::SECTION_PROCUREMENT, true, $user);

        $this->assertTrue($service->isEnabled(FeatureRegistry::FEATURE_REQUESTS));
        $this->assertFalse($service->isEnabled(FeatureRegistry::FEATURE_QUOTES));
    }

    public function test_missing_row_hydration_is_idempotent_and_does_not_create_audit_or_invalidate_cache(): void
    {
        $user = $this->authenticatedUser('Admin');
        $service = app(FeatureModuleService::class);
        FeatureFlag::query()->where('key', FeatureRegistry::FEATURE_ITEMS)->delete();
        Cache::put(FeatureModuleService::CACHE_KEY, ['sentinel' => true]);

        $service->toggleFeature(FeatureRegistry::FEATURE_ITEMS, true, $user);

        $this->assertDatabaseHas('feature_flags', [
            'key' => FeatureRegistry::FEATURE_ITEMS,
            'enabled' => true,
            'updated_by' => null,
        ]);
        $this->assertSame(['sentinel' => true], Cache::get(FeatureModuleService::CACHE_KEY));
        $this->assertSame(0, Activity::query()->where('log_name', 'feature-modules')->count());
    }

    public function test_feature_flag_seeder_preserves_an_administrator_disabled_value(): void
    {
        $flag = FeatureFlag::query()->where('key', FeatureRegistry::FEATURE_ITEMS)->firstOrFail();
        $flag->update(['enabled' => false]);

        $this->seed(FeatureFlagSeeder::class);

        $this->assertDatabaseHas('feature_flags', [
            'key' => FeatureRegistry::FEATURE_ITEMS,
            'enabled' => false,
        ]);
    }

    public function test_disabled_feature_removes_resource_access_but_keeps_sibling_features_available(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);

        Filament::setCurrentPanel('admin');
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', ProcurementItem::class));
        $this->assertFalse(ProcurementItemResource::canViewAny());
        $this->assertTrue(ProcurementCategoryResource::canViewAny());
    }

    public function test_disabled_feature_blocks_attachment_authorization_for_its_owner(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_REQUESTS, false, $user);
        $attachment = Attachment::make(['attachable_type' => PurchaseRequest::class]);

        $this->assertFalse(Gate::forUser($user)->allows('download', $attachment));
    }

    public function test_disabled_feature_forbids_direct_resource_index_access(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);

        $this->get('/admin/procurement-items')->assertForbidden();
    }

    public function test_feature_modules_page_remains_reachable_when_settings_section_is_disabled(): void
    {
        $user = $this->authenticatedUser('Admin');
        $service = app(FeatureModuleService::class);
        $service->toggleSection(FeatureRegistry::SECTION_SETTINGS, false, $user);

        $this->get('/admin/feature-modules')->assertOk();
    }

    public function test_feature_modules_page_requires_feature_manager_permission(): void
    {
        $this->authenticatedUser('Viewer');

        $this->get('/admin/feature-modules')->assertForbidden();
    }

    public function test_disabled_activity_logging_rejects_toggle_and_preserves_cached_state(): void
    {
        $user = $this->authenticatedUser('Admin');
        Cache::put(FeatureModuleService::CACHE_KEY, ['sentinel' => true]);
        config(['activitylog.enabled' => false]);

        $thrown = false;
        try {
            app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(['sentinel' => true], Cache::get(FeatureModuleService::CACHE_KEY));
        $this->assertDatabaseHas('feature_flags', [
            'key' => FeatureRegistry::FEATURE_ITEMS,
            'enabled' => true,
        ]);
        $this->assertSame(0, Activity::query()->where('log_name', 'feature-modules')->count());
    }

    public function test_audit_failure_rolls_back_feature_state_and_preserves_cached_state(): void
    {
        $user = $this->authenticatedUser('Admin');
        Cache::put(FeatureModuleService::CACHE_KEY, ['sentinel' => true]);
        Activity::creating(static function (): void {
            throw new \RuntimeException('Audit write failed.');
        });

        $thrown = false;
        try {
            app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertDatabaseHas('feature_flags', [
            'key' => FeatureRegistry::FEATURE_ITEMS,
            'enabled' => true,
            'updated_by' => null,
        ]);
        $this->assertSame(['sentinel' => true], Cache::get(FeatureModuleService::CACHE_KEY));
    }

    public function test_disabled_budget_feature_rejects_reservation_before_business_write(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_BUDGETS, false, $user);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Fitur sedang dinonaktifkan administrator.');
        app(BudgetReservationService::class)->reserve(PurchaseRequest::make(), actor: $user);
    }

    public function test_disabled_approval_feature_rejects_approval_creation(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleFeature(FeatureRegistry::FEATURE_APPROVAL_INBOX, false, $user);

        $this->expectException(AuthorizationException::class);
        app(ApprovalInstanceCreator::class)->create(
            PurchaseRequest::make(),
            $user,
            ['reference' => 'test', 'steps' => []],
        );
    }

    public function test_disabled_approval_inbox_makes_sla_processing_a_successful_noop(): void
    {
        $user = $this->authenticatedUser('Admin');
        app(FeatureModuleService::class)->toggleSection(FeatureRegistry::SECTION_APPROVALS, false, $user);

        $this->assertSame(
            ['warnings' => 0, 'expired' => 0, 'escalated' => 0],
            app(ApprovalTaskLifecycleService::class)->processSla(),
        );
    }

    public function test_user_without_feature_manager_permission_cannot_toggle_a_feature(): void
    {
        $user = $this->authenticatedUser('Viewer');
        $service = app(FeatureModuleService::class);

        $this->assertFalse($service->canManage($user));

        $this->expectException(AuthorizationException::class);
        $service->toggleFeature(FeatureRegistry::FEATURE_ITEMS, false, $user);
    }

    private function authenticatedUser(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $office = Office::factory()->create();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role' => $role,
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        app(AccessContextService::class)->setContext($assignment);

        return $user;
    }
}
