<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccessContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_default_context_and_persists_only_the_validated_assignment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $branch = Branch::factory()->create(['office_id' => $office->id]);
        $department = Department::factory()->create(['office_id' => $office->id, 'branch_id' => $branch->id]);
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'role_id' => $role->id,
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        $context = app(AccessContextService::class);

        $this->assertTrue($context->assignment()->is($assignment));
        $this->assertSame($office->id, $context->id());
        $this->assertSame($branch->id, $context->branch()?->id);
        $this->assertSame($department->id, $context->department()?->id);
        $this->assertSame('Operasional', $context->roleName());
        $this->assertSame($assignment->id, session(AccessContextService::SESSION_KEY)['assignment_id']);
    }

    public function test_expired_or_forged_context_is_rejected_and_default_is_selected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $default = Office::factory()->create();
        $expired = Office::factory()->create();
        $defaultAssignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $default->id,
            'is_primary' => true,
        ]);
        $expiredAssignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $expired->id,
            'valid_until' => Carbon::yesterday(),
        ]);

        session()->put(AccessContextService::SESSION_KEY, [
            'assignment_id' => $expiredAssignment->id,
            'office_id' => $expired->id,
            'branch_id' => null,
            'department_id' => null,
            'role_id' => null,
        ]);

        $this->actingAs($user);
        $context = app(AccessContextService::class);

        $this->assertTrue($context->assignment()->is($defaultAssignment));
        $this->assertSame($default->id, $context->id());
        $this->assertNotSame($expired->id, session(AccessContextService::SESSION_KEY)['office_id']);
    }

    public function test_switching_requires_an_active_assignment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $office = Office::factory()->create();
        UserAssignment::factory()->create(['user_id' => $user->id, 'office_id' => $office->id, 'is_primary' => true]);

        $this->actingAs($user);
        $context = app(AccessContextService::class);

        $this->expectException(\InvalidArgumentException::class);
        $context->setContext(['assignment_id' => 999999, 'office_id' => 999999]);
    }
}
