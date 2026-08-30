<?php

namespace Tests\Feature;

use App\Filament\Resources\VendorResource;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorItem;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_resource_is_registered_and_master_data_mutations_are_scoped(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $vendor = Vendor::factory()->create();
        $vendorItem = VendorItem::factory()->create(['vendor_id' => $vendor->id]);

        $this->assertContains(VendorResource::class, $panel->getResources());
        $this->assertSame(Vendor::class, VendorResource::getModel());
        $this->assertTrue($admin->can('viewAny', Vendor::class));
        $this->assertTrue($admin->can('create', Vendor::class));
        $this->assertTrue($admin->can('update', $vendor));
        $this->assertTrue($admin->can('viewSensitiveData', $vendor));
        $this->assertTrue($viewer->can('viewAny', Vendor::class));
        $this->assertFalse($viewer->can('create', Vendor::class));
        $this->assertFalse($viewer->can('update', $vendor));
        $this->assertFalse($viewer->can('viewSensitiveData', $vendor));
        $this->assertTrue($admin->can('view', $vendorItem));
        $this->assertTrue($admin->can('update', $vendorItem));
        $this->assertFalse($viewer->can('update', $vendorItem));
    }

    public function test_vendor_form_exposes_validated_master_fields_and_item_links(): void
    {
        $components = [];
        $schema = \Mockery::mock(Schema::class);
        $schema
            ->shouldReceive('components')
            ->once()
            ->withArgs(function (array $provided) use (&$components): bool {
                $components = $provided;

                return true;
            })
            ->andReturnSelf();
        $schema->shouldReceive('columns')->once()->with(2)->andReturnSelf();

        VendorResource::form($schema);

        $componentNames = collect($components)
            ->map(fn (object $component): string => $component->getName())
            ->all();

        $this->assertSame(
            ['code', 'name', 'vendor_type', 'is_active', 'contact_name', 'phone', 'email', 'tax_number', 'address', 'items'],
            $componentNames,
        );
        $this->assertInstanceOf(Repeater::class, $components[9]);
    }

    public function test_vendor_deactivation_is_authorized_and_does_not_delete_supplied_items(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $vendor = Vendor::factory()->create();
        $vendorItem = VendorItem::factory()->create(['vendor_id' => $vendor->id]);

        $this->assertTrue($admin->can('deactivate', $vendor));
        $this->assertFalse(User::factory()->create()->can('deactivate', $vendor));

        $vendor->deactivate();

        $this->assertModelExists($vendor->refresh());
        $this->assertModelExists($vendorItem->refresh());
        $this->assertFalse($vendor->is_active);
        $this->assertFalse($admin->can('deactivate', $vendor));
        $this->assertTrue($admin->can('activate', $vendor));
        $this->assertFalse($admin->can('delete', $vendor));
    }
}
