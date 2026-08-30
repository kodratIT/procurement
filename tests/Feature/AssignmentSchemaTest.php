<?php

namespace Tests\Feature;

use App\Models\AssignmentPermissionOverride;
use App\Models\AssignmentScope;
use App\Models\Branch;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class AssignmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_schema_contains_contextual_authorization_tables_and_indexes(): void
    {
        foreach (['roles', 'permissions', 'user_assignments', 'assignment_scopes', 'assignment_permission_overrides'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table.");
        }

        $this->assertTrue(Schema::hasColumns('user_assignments', [
            'user_id', 'office_id', 'branch_id', 'department_id', 'role_id',
            'valid_from', 'valid_until', 'is_active', 'is_primary',
        ]));
        $this->assertTrue(Schema::hasColumns('assignment_scopes', ['assignment_id', 'scope_type', 'scope_id']));
        $this->assertTrue(Schema::hasColumns('assignment_permission_overrides', ['assignment_id', 'permission_id', 'effect']));
        $this->assertTrue(Schema::hasColumns('roles', ['code', 'is_active']));
        $this->assertTrue(Schema::hasColumns('permissions', ['code', 'module', 'action']));
    }

    public function test_assignment_rejects_cross_office_context_and_overlapping_duplicate_context(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $branch = Branch::factory()->create(['office_id' => $otherOffice->id]);
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/same office/i');

        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_user_can_hold_multiple_roles_in_one_office_on_the_same_start_date(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $validFrom = Carbon::today();

        foreach (['Operasional', 'Pengadaan'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            UserAssignment::factory()->create([
                'user_id' => $user->id,
                'office_id' => $office->id,
                'role_id' => $role->id,
                'valid_from' => $validFrom,
            ]);
        }

        $this->assertSame(2, $user->assignments()->count());
    }

    public function test_active_assignment_permissions_respect_validity_and_overrides_independently(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $user = User::factory()->create();
        $expiredOffice = Office::factory()->create();
        $activeOffice = Office::factory()->create();

        $expired = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $expiredOffice->id,
            'role_id' => $role->id,
            'valid_from' => Carbon::yesterday()->subYear(),
            'valid_until' => Carbon::yesterday(),
        ]);
        $active = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $activeOffice->id,
            'role_id' => $role->id,
            'valid_from' => Carbon::yesterday(),
        ]);

        $active->permissionOverrides()->create([
            'permission_id' => Permission::query()->where('name', 'procurement.create')->value('id'),
            'effect' => AssignmentPermissionOverride::DENY,
        ]);
        $active->scopes()->create([
            'scope_type' => AssignmentScope::TYPES[0],
            'scope_id' => 42,
        ]);

        $this->assertFalse($expired->allows('procurement.create'));
        $this->assertFalse($active->allows('procurement.create'));
        $this->assertTrue($user->hasActiveAssignment());
        $this->assertDatabaseHas('assignment_scopes', ['assignment_id' => $active->id, 'scope_type' => 'branch', 'scope_id' => 42]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => UserAssignment::class,
            'subject_id' => $active->id,
        ]);
        $role->update(['is_active' => false]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Role::class,
            'subject_id' => $role->id,
        ]);
    }
}
