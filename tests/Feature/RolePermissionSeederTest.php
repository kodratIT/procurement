<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Support\ProcurementPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_required_roles_and_module_action_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->assertEqualsCanonicalizing(
            ['Admin', 'Operasional', 'Pengadaan', 'Keuangan', 'Manager', 'Manajemen', 'Auditor'],
            Role::query()->whereIn('name', [
                'Admin', 'Operasional', 'Pengadaan', 'Keuangan', 'Manager', 'Manajemen', 'Auditor',
            ])->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ProcurementPermissions::all(),
            Permission::query()->pluck('name')->all(),
        );

        $permission = Permission::query()->where('name', ProcurementPermissions::APPROVE)->firstOrFail();
        $this->assertSame('procurement', $permission->module);
        $this->assertSame('approve', $permission->action);
        $this->assertTrue(Role::query()->where('name', 'Admin')->firstOrFail()->hasPermissionTo($permission));
    }

    public function test_seeder_is_repeatable_without_duplicate_rbac_records(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(8, Role::query()->count());
        $this->assertSame(count(ProcurementPermissions::all()), Permission::query()->count());
    }
}
