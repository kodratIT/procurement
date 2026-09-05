<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Support\ProcurementPermissions;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_procurement_permissions_and_standard_roles(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $adminPerms = Role::query()->where('name', 'Admin')->firstOrFail()->permissions->pluck('name')->all();
        foreach (ProcurementPermissions::all() as $perm) {
            $this->assertContains($perm, $adminPerms);
        }

        $roles = Role::query()->pluck('name')->all();
        foreach (['Operasional', 'Pengadaan', 'Keuangan', 'Manager', 'Admin', 'Auditor', 'Viewer'] as $expected) {
            $this->assertContains($expected, $roles);
        }

        $allPerms = Permission::query()->pluck('name')->all();
        foreach (ProcurementPermissions::all() as $perm) {
            $this->assertContains($perm, $allPerms);
        }
    }

    public function test_users_in_different_offices_keep_their_distinct_procurement_roles(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $jakarta = Office::query()->create(['code' => 'JKT', 'name' => 'Jakarta']);
        $jambi = Office::query()->create(['code' => 'JBI', 'name' => 'Jambi']);
        $operasional = User::factory()->create(['email' => 'operasional@example.test']);
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        $operasional->offices()->attach($jakarta);
        $manager->offices()->attach($jambi);
        $operasional->assignRole('Operasional');
        $manager->assignRole('Manager');

        $this->assertTrue($operasional->offices()->whereKey($jakarta)->exists());
        $this->assertFalse($operasional->offices()->whereKey($jambi)->exists());
        $this->assertTrue($manager->offices()->whereKey($jambi)->exists());
        $this->assertFalse($manager->offices()->whereKey($jakarta)->exists());
        $this->assertTrue($operasional->can(ProcurementPermissions::CREATE));
        $this->assertFalse($operasional->can(ProcurementPermissions::APPROVE));
        $this->assertTrue($manager->can(ProcurementPermissions::APPROVE));
        $this->assertFalse($manager->can(ProcurementPermissions::CREATE));
    }
}
