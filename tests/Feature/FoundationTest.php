<?php

namespace Tests\Feature;

use App\Models\ProcurementCategory;
use App\Models\ProcurementUnit;
use Database\Seeders\ProcurementMasterSeeder;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_primary_application_routes_are_registered(): void
    {
        $this->assertNotNull(route('keycloak.redirect'));
        $this->assertNotNull(route('keycloak.callback'));
        $this->assertNotNull(route('logout'));
    }

    public function test_filament_admin_panel_boots(): void
    {
        $panel = app('filament')->getPanel('admin');

        $this->assertSame('admin', $panel->getId());
        $this->assertSame('admin', $panel->getPath());
        $this->get('/admin/login')->assertOk();
    }

    public function test_foundation_seeders_provide_roles_and_master_data(): void
    {
        $this->seed([
            ProcurementRolesSeeder::class,
            ProcurementMasterSeeder::class,
        ]);

        $this->assertDatabaseHas('roles', ['name' => 'Viewer', 'guard_name' => 'web']);
        $this->assertGreaterThan(0, ProcurementCategory::query()->count());
        $this->assertGreaterThan(0, ProcurementUnit::query()->count());
    }
}
