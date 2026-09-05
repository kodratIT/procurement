<?php

namespace Tests\Feature;

use App\Models\AssignmentScope;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOfficeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_context_scopes_reads_and_role_controls_writes(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, $officeB, $assignmentA, $assignmentB] = $this->userWithTwoRoles();
        $requestA = PurchaseRequest::factory()->create(['office_id' => $officeA->id]);
        $requestB = PurchaseRequest::factory()->create(['office_id' => $officeB->id]);

        $this->actingAs($user);
        app(AccessContextService::class)->setContext($assignmentA);
        $authorization = app(MultiOfficeAuthorization::class);

        $currentContextIds = $authorization
            ->scopeForCurrentContext(PurchaseRequest::query(), $user)
            ->pluck('id');
        $this->assertTrue($currentContextIds->contains($requestA->id));
        $this->assertFalse($currentContextIds->contains($requestB->id));
        $this->assertTrue($authorization->canUpdate($user, $requestA, true));

        app(AccessContextService::class)->setContext($assignmentB);
        $this->assertTrue($authorization->canUpdate($user, $requestB, true));
        $this->assertTrue($authorization->canUpdate($user, $requestA, true));
    }

    public function test_forged_office_fields_cannot_create_a_transaction(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, $officeB] = $this->userWithTwoRoles();
        $unassignedOffice = Office::factory()->create();
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        PurchaseRequest::factory()->create(['office_id' => $unassignedOffice->id]);
    }

    public function test_non_default_office_mutations_do_not_require_confirmation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, $officeB, $assignmentA, $assignmentB] = $this->userWithTwoRoles();
        $requestB = PurchaseRequest::factory()->create(['office_id' => $officeB->id]);

        $this->actingAs($user);
        $context = app(AccessContextService::class);
        $context->setContext($assignmentB);
        $authorization = app(MultiOfficeAuthorization::class);

        $this->assertTrue($context->requiresConfirmation());
        $this->assertTrue($authorization->canUpdate($user, $requestB));
    }

    public function test_category_scoped_assignment_only_reads_allowed_categories(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, , , $assignmentB] = $this->userWithTwoRoles();
        $allowedCategory = ProcurementCategory::factory()->create();
        $blockedCategory = ProcurementCategory::factory()->create();
        AssignmentScope::create([
            'assignment_id' => $assignmentB->id,
            'scope_type' => 'category',
            'scope_id' => $allowedCategory->id,
        ]);
        $allowedRequest = PurchaseRequest::factory()->create([
            'office_id' => $assignmentB->office_id,
            'category_id' => $allowedCategory->id,
        ]);
        $blockedRequest = PurchaseRequest::factory()->create([
            'office_id' => $assignmentB->office_id,
            'category_id' => $blockedCategory->id,
        ]);

        $this->actingAs($user);
        $ids = app(MultiOfficeAuthorization::class)
            ->scopeForUser(PurchaseRequest::query(), $user, 'ViewAny:PurchaseRequest')
            ->pluck('id')
            ->all();

        $this->assertContains($allowedRequest->id, $ids);
        $this->assertNotContains($blockedRequest->id, $ids);
    }

    /** @return array{User, Office, Office, UserAssignment, UserAssignment} */
    private function userWithTwoRoles(): array
    {
        $user = User::factory()->create();
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $operational = Role::query()->where('name', 'Operasional')->firstOrFail();
        $procurement = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $assignmentA = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $officeA->id,
            'role_id' => $operational->id,
            'is_primary' => true,
        ]);
        $assignmentB = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $officeB->id,
            'role_id' => $procurement->id,
        ]);

        return [$user, $officeA, $officeB, $assignmentA, $assignmentB];
    }
}
