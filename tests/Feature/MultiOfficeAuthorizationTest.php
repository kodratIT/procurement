<?php

namespace Tests\Feature;

use App\Models\Office;
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
        $authorization = app(MultiOfficeAuthorization::class);

        $this->assertTrue(PurchaseRequest::query()->pluck('id')->contains($requestA->id));
        $this->assertFalse(PurchaseRequest::query()->pluck('id')->contains($requestB->id));
        $this->assertFalse($authorization->canUpdate($user, $requestA, true));

        app(AccessContextService::class)->setContext($assignmentB);
        $this->assertTrue($authorization->canUpdate($user, $requestB, true));
        $this->assertFalse($authorization->canUpdate($user, $requestA, true));
    }

    public function test_forged_office_fields_cannot_create_a_transaction(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, $officeB] = $this->userWithTwoRoles();
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        PurchaseRequest::factory()->create(['office_id' => $officeB->id]);
    }

    public function test_non_default_office_mutations_require_confirmation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$user, $officeA, $officeB, $assignmentA, $assignmentB] = $this->userWithTwoRoles();
        $requestB = PurchaseRequest::factory()->create(['office_id' => $officeB->id]);

        $this->actingAs($user);
        $context = app(AccessContextService::class);
        $context->setContext($assignmentB);
        $authorization = app(MultiOfficeAuthorization::class);

        $this->assertTrue($context->requiresConfirmation());
        $this->assertFalse($authorization->canUpdate($user, $requestB));
        $context->confirmMutation();
        $this->assertTrue($authorization->canUpdate($user, $requestB));
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
