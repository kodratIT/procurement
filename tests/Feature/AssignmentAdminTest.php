<?php

namespace Tests\Feature;

use App\Filament\Resources\UserAssignments\UserAssignmentResource;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ProcurementPermissions;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_assignments_but_viewer_cannot(): void
    {
        $this->seed(RolePermissionSeeder::class);
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

        $this->assertTrue($admin->can('viewAny', UserAssignment::class));
        $this->assertTrue($admin->can('create', UserAssignment::class));
        $this->assertTrue($admin->can(ProcurementPermissions::MANAGE_USERS));
        $this->assertFalse($viewer->can('viewAny', UserAssignment::class));
    }

    public function test_assignment_resource_is_registered_with_individual_and_bulk_management(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $panel = Filament::getPanel('admin');

        $this->assertContains(UserAssignmentResource::class, $panel->getResources());
        $this->assertSame(UserAssignment::class, UserAssignmentResource::getModel());

        $user = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
        ]);

        $assignment->update(['is_active' => false]);
        $this->assertFalse($assignment->fresh()->is_active);
        $assignment->update(['is_active' => true]);
        $this->assertTrue($assignment->fresh()->is_active);
    }
}
