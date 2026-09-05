<?php

namespace Tests\Feature;

use App\Filament\Resources\ProcurementItemResource;
use App\Filament\Resources\ProcurementUnitResource;
use App\Filament\Resources\ProcurementVariantResource;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCatalogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_resources_are_registered_and_require_master_data_permission(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $office = Office::factory()->create();
        $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();
        UserAssignment::factory()->create(['user_id' => $admin->id, 'office_id' => $office->id, 'role_id' => $adminRole->id, 'is_primary' => true]);
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $viewerRole = Role::query()->where('name', 'Viewer')->firstOrFail();
        $viewerOffice = Office::factory()->create();
        UserAssignment::factory()->create(['user_id' => $viewer->id, 'office_id' => $viewerOffice->id, 'role_id' => $viewerRole->id, 'is_primary' => true]);

        $this->assertContains(ProcurementItemResource::class, $panel->getResources());
        $this->assertContains(ProcurementUnitResource::class, $panel->getResources());
        $this->assertContains(ProcurementVariantResource::class, $panel->getResources());
        $this->assertSame(ProcurementItem::class, ProcurementItemResource::getModel());
        $this->assertSame(ProcurementUnit::class, ProcurementUnitResource::getModel());
        $this->assertSame(ProcurementVariant::class, ProcurementVariantResource::getModel());
        $this->assertTrue($admin->can('viewAny', ProcurementItem::class));
        $this->assertTrue($admin->can('create', ProcurementUnit::class));
        $this->assertFalse($viewer->can('viewAny', ProcurementVariant::class));
    }

    public function test_catalog_records_can_be_deactivated_without_being_deleted(): void
    {
        $item = ProcurementItem::factory()->create();
        $unit = $item->unit;
        $variant = ProcurementVariant::factory()->create(['item_id' => $item->id]);

        $item->deactivate();
        $unit->deactivate();
        $variant->deactivate();

        $this->assertModelExists($item->refresh());
        $this->assertModelExists($unit->refresh());
        $this->assertModelExists($variant->refresh());
        $this->assertFalse($item->is_active);
        $this->assertFalse($unit->is_active);
        $this->assertFalse($variant->is_active);
    }

    public function test_catalog_delete_policy_protects_historical_references(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $office = Office::factory()->create();
        $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();
        UserAssignment::factory()->create(['user_id' => $admin->id, 'office_id' => $office->id, 'role_id' => $adminRole->id, 'is_primary' => true]);
        $item = ProcurementItem::factory()->create();
        $variant = ProcurementVariant::factory()->create(['item_id' => $item->id]);

        $this->assertFalse($admin->can('delete', $item));
        $this->assertFalse($admin->can('delete', $item->unit));
        $this->assertTrue($admin->can('delete', $variant));
    }
}
