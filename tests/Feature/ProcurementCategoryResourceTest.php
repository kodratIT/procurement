<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProcurementCategoryResource;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProcurementCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_is_registered_and_configuration_permission_is_required(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->assertContains(ProcurementCategoryResource::class, $panel->getResources());
        $this->assertSame(ProcurementCategory::class, ProcurementCategoryResource::getModel());
        $this->assertTrue($admin->can('viewAny', ProcurementCategory::class));
        $this->assertTrue($admin->can('create', ProcurementCategory::class));
        $this->assertFalse($viewer->can('viewAny', ProcurementCategory::class));
        $this->assertFalse($admin->can('deleteAny', ProcurementCategory::class));
    }

    public function test_unused_category_can_be_deleted_by_a_configured_admin(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $category = ProcurementCategory::factory()->create();

        $this->assertTrue($admin->can('delete', $category));

        $category->delete();

        $this->assertModelMissing($category);
    }

    public function test_resource_query_uses_the_active_assignment_scope(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $office = Office::factory()->create();
        $roleId = Role::query()->where('name', 'Admin')->value('id');
        UserAssignment::factory()->create([
            'user_id' => $admin->id,
            'office_id' => $office->id,
            'role_id' => $roleId,
            'role' => 'Admin',
            'is_primary' => true,
        ]);
        ProcurementCategory::factory()->create(['code' => 'GLOBAL-ONE']);
        ProcurementCategory::factory()->create(['code' => 'GLOBAL-TWO']);
        Auth::login($admin);

        $codes = ProcurementCategoryResource::getEloquentQuery()
            ->pluck('code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['GLOBAL-ONE', 'GLOBAL-TWO'], $codes);
    }

    public function test_activation_actions_preserve_the_category_record(): void
    {
        $category = ProcurementCategory::factory()->create();
        $category->deactivate();

        $category->activate();

        $this->assertModelExists($category->refresh());
        $this->assertTrue($category->is_active);
        $this->assertNull($category->disabled_at);
    }
}
