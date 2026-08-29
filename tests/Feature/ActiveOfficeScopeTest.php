<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveOffice;
use App\Models\DepartureBatch;
use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ActiveOfficeContext;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActiveOfficeScopeTest extends TestCase
{
    use RefreshDatabase;

    private function assignedUser(Office $office, string $role = 'Viewer'): User
    {
        $user = User::factory()->create();
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'valid_until' => null,
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $user;
    }

    private function seedRole(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name, 'web');
        $role->syncPermissions(
            collect($permissions)->map(fn (string $p) => Permission::findOrCreate($p, 'web'))->all(),
        );

        return $role;
    }

    public function test_switch_route_rejects_office_outside_assignment(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        $user = $this->assignedUser($office);
        $user->assignRole('Viewer');

        $this->actingAs($user);

        $this->post(route('office.switch'), ['office_id' => $other->id])
            ->assertForbidden();

        $this->assertSame($office->id, app(ActiveOfficeContext::class)->id());
    }

    public function test_switch_route_accepts_assigned_office(): void
    {
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        $user = $this->assignedUser($office);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $other->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->post(route('office.switch'), ['office_id' => $other->id])
            ->assertRedirect();

        $this->assertSame($other->id, app(ActiveOfficeContext::class)->id());
    }

    public function test_global_scope_isolates_departure_batches_to_active_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = $this->assignedUser($officeA);

        DepartureBatch::factory()->create(['office_id' => $officeA->id, 'code' => 'A-1']);
        DepartureBatch::factory()->create(['office_id' => $officeB->id, 'code' => 'B-1']);

        $this->actingAs($user);

        $visible = DepartureBatch::query()->pluck('code')->all();
        $this->assertSame(['A-1'], $visible);
    }

    public function test_for_office_scope_denies_other_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = $this->assignedUser($officeA);

        $this->actingAs($user);

        try {
            DepartureBatch::query()->forOffice($officeB->id)->get();
            $this->fail('Expected a 403 for an office outside the assignment.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_across_offices_scope_requires_manage_users_permission(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = $this->assignedUser($officeA);

        DepartureBatch::factory()->create(['office_id' => $officeA->id, 'code' => 'A-1']);
        DepartureBatch::factory()->create(['office_id' => $officeB->id, 'code' => 'B-1']);

        $user->assignRole('Viewer');
        $this->actingAs($user);

        // Viewer cannot see across offices.
        try {
            DepartureBatch::query()->acrossOffices()->get();
            $this->fail('Expected a 403 for cross-office access without permission.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_across_offices_scope_allows_manage_users(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = $this->assignedUser($officeA);

        DepartureBatch::factory()->create(['office_id' => $officeA->id, 'code' => 'A-1']);
        DepartureBatch::factory()->create(['office_id' => $officeB->id, 'code' => 'B-1']);

        $user->assignRole('Admin');
        $this->actingAs($user);

        $codes = DepartureBatch::query()->acrossOffices()->pluck('code')->all();
        $this->assertSame(['A-1', 'B-1'], $codes);
    }

    public function test_panel_blocks_user_with_expired_assignment(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        // Assignment exists but expired → hasActiveAssignment() false → panel denied.
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday()->subYear(),
            'valid_until' => Carbon::yesterday()->subDay(),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->get('/admin/departure-batches')->assertForbidden();
    }

    public function test_active_office_middleware_redirects_when_no_current_office(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/some-protected-page', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureActiveOffice;
        $response = $middleware->handle($request, fn () => response('ok'), 'filament.admin.pages.office-switcher');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('filament.admin.pages.office-switcher'), $response->getTargetUrl());
    }

    public function test_switcher_page_reachable_when_session_office_is_stale(): void
    {
        $office = Office::factory()->create();
        $stale = Office::factory()->create();
        $user = User::factory()->create();
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);
        $this->actingAs($user);
        session([ActiveOfficeContext::SESSION_KEY => $stale->id]);

        // The user has a valid assignment but the session points at an office
        // they cannot access. The switcher must still be reachable so they
        // can pick a valid one.
        $this->get('/admin/office-switcher')->assertOk();
    }

    public function test_available_offices_only_returns_assigned_active_offices(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $disabled = Office::factory()->disabled()->create();
        $user = $this->assignedUser($officeA);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $officeB->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $disabled->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $ids = app(ActiveOfficeContext::class)->availableOffices()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$officeA->id, $officeB->id], $ids);
    }

    public function test_switcher_page_lists_offices_and_switches(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = $this->assignedUser($officeA);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $officeB->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('filament.admin.pages.office-switcher'));
        $response->assertOk();
        $response->assertSee($officeA->name);
        $response->assertSee($officeB->name);
    }

    public function test_creating_scoped_record_assigns_active_office_automatically(): void
    {
        $office = Office::factory()->create();
        $user = $this->assignedUser($office);

        $this->actingAs($user);

        $batch = DepartureBatch::factory()->create(['code' => 'AUTO-1', 'office_id' => null]);

        $this->assertSame($office->id, $batch->fresh()->office_id);
    }

    public function test_office_policy_gates_view_all_offices(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $viewer = $this->assignedUser($office);
        $admin = $this->assignedUser($office);

        $viewer->assignRole('Viewer');
        $admin->assignRole('Admin');

        $this->assertFalse($viewer->can('viewAllOffices'));
        $this->assertTrue($admin->can('viewAllOffices'));
    }

    public function test_middleware_blocks_panel_without_any_assignment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // hasActiveAssignment() is false → Filament itself blocks panel access.
        $this->get('/admin/departure-batches')->assertForbidden();
    }
}
