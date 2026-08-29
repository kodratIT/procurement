<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Exports\DepartureBatchExporter;
use App\Filament\Exports\ProcurementCategoryExporter;
use App\Filament\Exports\ProcurementItemExporter;
use App\Filament\Exports\ProcurementUnitExporter;
use App\Filament\Exports\ProcurementVariantExporter;
use App\Filament\Exports\UserAssignmentExporter;
use App\Filament\Exports\VendorExporter;
use App\Models\Activity;
use App\Models\DepartureBatch;
use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PluginFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shield_role_resource_is_registered_on_admin_panel(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $panel = Filament::getPanel('admin');

        $this->assertNotNull($panel);
        $this->assertContains(
            RoleResource::class,
            $panel->getResources(),
        );
    }

    public function test_activity_resource_is_registered_on_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertNotNull($panel);
        $this->assertContains(
            ActivityResource::class,
            $panel->getResources(),
        );
    }

    public function test_activity_policy_gates_activity_log_by_procurement_view_permission(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $viewer = User::factory()->create(['email' => 'viewer@example.test']);
        $viewer->assignRole('Viewer');

        $outsider = User::factory()->create(['email' => 'outsider@example.test']);

        $activity = Activity::query()->create([
            'log_name' => 'Resource',
            'description' => 'created vendor',
            'subject_type' => Vendor::class,
            'subject_id' => 1,
            'event' => 'created',
        ]);

        $this->assertTrue($viewer->can('viewAny', Activity::class));
        $this->assertTrue($viewer->can('view', $activity));
        $this->assertFalse($outsider->can('viewAny', Activity::class));
        $this->assertFalse($outsider->can('view', $activity));
    }

    public function test_activity_policy_export_ability_requires_procurement_export_permission(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $keuangan = User::factory()->create(['email' => 'keuangan@example.test']);
        $keuangan->assignRole('Keuangan');

        $viewer = User::factory()->create(['email' => 'viewer2@example.test']);
        $viewer->assignRole('Viewer');

        $this->assertTrue($keuangan->can('exportActivity', Activity::class));
        $this->assertFalse($viewer->can('exportActivity', Activity::class));
    }

    public function test_exporter_classes_are_registered_for_each_resource(): void
    {
        $exporters = [
            VendorExporter::class => Vendor::class,
            ProcurementCategoryExporter::class => ProcurementCategory::class,
            ProcurementUnitExporter::class => ProcurementUnit::class,
            ProcurementItemExporter::class => ProcurementItem::class,
            ProcurementVariantExporter::class => ProcurementVariant::class,
            DepartureBatchExporter::class => DepartureBatch::class,
            UserAssignmentExporter::class => UserAssignment::class,
        ];

        foreach ($exporters as $exporter => $model) {
            $this->assertSame($model, $exporter::getModel());
            $this->assertNotEmpty($exporter::getColumns(), "{$exporter} should define export columns");
        }
    }

    public function test_permission_tables_work_with_spatie_roles_and_shield_roles_coexist(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $role = Role::findOrCreate('super_admin', 'web');
        $permission = Permission::findOrCreate('view procurement', 'web');
        $role->givePermissionTo($permission);

        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->assignRole('super_admin');

        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->can('view procurement'));
        $this->assertTrue($admin->hasPermissionTo('view procurement'));
    }

    public function test_role_policy_restricts_role_management_to_manage_roles_permission(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $admin = User::factory()->create(['email' => 'admin2@example.test']);
        $admin->assignRole('Admin');

        $viewer = User::factory()->create(['email' => 'viewer3@example.test']);
        $viewer->assignRole('Viewer');

        $role = Role::query()->where('name', 'Manager')->firstOrFail();

        $this->assertTrue($admin->can('viewAny', Role::class));
        $this->assertTrue($admin->can('update', $role));
        $this->assertFalse($viewer->can('viewAny', Role::class));
        $this->assertFalse($viewer->can('update', $role));
    }

    public function test_super_admin_role_bypasses_role_policy(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        Role::findOrCreate('super_admin', 'web');

        $superAdmin = User::factory()->create(['email' => 'super@example.test']);
        $superAdmin->assignRole('super_admin');

        $role = Role::query()->where('name', 'Manager')->firstOrFail();

        $this->assertTrue($superAdmin->can('viewAny', Role::class));
        $this->assertTrue($superAdmin->can('update', $role));
    }
}
