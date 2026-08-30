<?php

namespace Tests\Feature;

use App\Filament\Exports\VendorExporter;
use App\Filament\Resources\VendorResource;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Models\VendorItem;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Actions\ExportBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_vendor_export_requires_permission_and_hides_sensitive_columns(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        Auth::login($auditor);
        $safeColumns = collect(VendorExporter::getVisibleColumns())
            ->map(fn (object $column): string => $column->getName())
            ->values()
            ->all();

        $this->assertSame(['code', 'name', 'is_active', 'created_at'], $safeColumns);

        $toolbarActions = [];
        $table = \Mockery::mock(Table::class);
        $table->shouldReceive('columns')->once()->andReturnSelf();
        $table->shouldReceive('recordActions')->once()->andReturnSelf();
        $table->shouldReceive('toolbarActions')
            ->once()
            ->withArgs(function (array $actions) use (&$toolbarActions): bool {
                $toolbarActions = $actions;

                return true;
            })
            ->andReturnSelf();

        VendorResource::table($table);
        $exportAction = collect($toolbarActions)
            ->first(fn (object $action): bool => $action instanceof ExportBulkAction);

        $this->assertInstanceOf(ExportBulkAction::class, $exportAction);
        $this->assertTrue($auditor->can('export', Vendor::class));
        $this->assertFalse($viewer->can('export', Vendor::class));

        Auth::login($viewer);
        $this->assertFalse($exportAction->getAuthorizationResponse()->allowed());

        Auth::login($admin);
        $sensitiveColumns = collect(VendorExporter::getVisibleColumns())
            ->map(fn (object $column): string => $column->getName())
            ->values()
            ->all();

        $this->assertSame(
            ['code', 'name', 'contact_name', 'phone', 'email', 'address', 'is_active', 'created_at'],
            $sensitiveColumns,
        );
        $this->assertTrue($exportAction->getAuthorizationResponse()->allowed());
    }

    public function test_vendor_resource_query_requires_an_active_view_assignment(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $assignedViewer = User::factory()->create();
        $assignedViewer->assignRole('Viewer');
        $unassignedViewer = User::factory()->create();
        $unassignedViewer->assignRole('Viewer');
        $office = Office::factory()->create();
        $viewerRole = Role::query()->where('name', 'Viewer')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $assignedViewer->id,
            'office_id' => $office->id,
            'role_id' => $viewerRole->id,
            'role' => 'Viewer',
            'is_primary' => true,
        ]);
        Vendor::factory()->create(['code' => 'VISIBLE-VENDOR']);

        Auth::login($assignedViewer);
        $this->assertSame(['VISIBLE-VENDOR'], VendorResource::getEloquentQuery()->pluck('code')->all());

        Auth::login($unassignedViewer);
        $this->assertSame([], VendorResource::getEloquentQuery()->pluck('code')->all());
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
