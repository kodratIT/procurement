<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\WorkflowResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class WorkflowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_resolution_returns_user_role_scope_and_fallback_explanation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$request, $step, $role] = $this->workflowFixture('role_in_request_office');
        $approver = User::factory()->create(['name' => 'Office approver']);
        UserAssignment::factory()->create([
            'user_id' => $approver->id,
            'office_id' => $request->office_id,
            'role_id' => $role->id,
        ]);
        $mapping = ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'role_in_request_office',
            'role_id' => $role->id,
            'user_id' => null,
            'office_id' => $request->office_id,
            'scope_source' => 'request_office',
        ]);

        $result = app(WorkflowResolver::class)->resolve($request, User::factory()->create());

        $this->assertSame($approver->id, $result['steps'][0]['approver_id']);
        $this->assertSame($role->name, $result['steps'][0]['approver_role']);
        $this->assertSame('request_office', $result['steps'][0]['scope_source']);
        $this->assertSame('none', $result['steps'][0]['fallback_result']);
        $this->assertSame($mapping->id, $result['steps'][0]['context']['mapping_id']);
        $this->assertSame($request->office_id, $result['context']['office_id']);
    }

    public function test_inactive_mapped_approver_uses_delegate_only_inside_validity_window_and_audits_it(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$request, $step, $role] = $this->workflowFixture('specific_user');
        $original = User::factory()->create(['is_active' => false]);
        $delegate = User::factory()->create(['name' => 'Temporary delegate']);
        UserAssignment::factory()->create([
            'user_id' => $original->id,
            'office_id' => $request->office_id,
            'role_id' => $role->id,
            'is_active' => false,
            'disabled_at' => now(),
        ]);
        UserAssignment::factory()->create([
            'user_id' => $delegate->id,
            'office_id' => $request->office_id,
            'role_id' => $role->id,
        ]);
        $mapping = ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'specific_user',
            'role_id' => null,
            'user_id' => $original->id,
            'office_id' => $request->office_id,
        ]);
        $delegation = ApproverDelegation::factory()->create([
            'delegator_id' => $original->id,
            'delegate_id' => $delegate->id,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $result = app(WorkflowResolver::class)->resolve($request, User::factory()->create());

        $this->assertSame($delegate->id, $result['steps'][0]['approver_id']);
        $this->assertSame($original->id, $result['steps'][0]['context']['delegated_from_user_id']);
        $this->assertSame($delegation->id, $result['steps'][0]['context']['delegation_id']);
        $this->assertTrue(Activity::query()->where('description', 'Approver delegation applied')->exists());

        $delegation->update(['valid_until' => Carbon::today()->subDay()]);
        $this->expectException(ValidationException::class);
        app(WorkflowResolver::class)->resolve($request->fresh(), User::factory()->create());
    }

    public function test_requester_cannot_be_resolved_as_approver_without_explicit_self_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$request, $step, $role] = $this->workflowFixture('nominal_role');
        UserAssignment::factory()->create([
            'user_id' => $request->requester_id,
            'office_id' => $request->office_id,
            'role_id' => $role->id,
        ]);
        ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'nominal_role',
            'role_id' => $role->id,
            'user_id' => null,
            'office_id' => $request->office_id,
        ]);

        $this->expectException(ValidationException::class);
        app(WorkflowResolver::class)->resolve($request, User::factory()->create());
    }

    /** @return array{0: PurchaseRequest, 1: WorkflowStep, 2: Role} */
    private function workflowFixture(string $resolverType): array
    {
        $office = Office::factory()->create();
        $requester = User::factory()->create();
        $role = Role::query()->where('name', 'Manager')->firstOrFail();
        $workflow = Workflow::create(['code' => 'WF-'.strtoupper($resolverType), 'name' => 'Test workflow']);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);
        $step = $version->steps()->create([
            'sequence' => 1,
            'name' => 'Approval step',
            'step_type' => 'approval',
            'resolver_type' => $resolverType,
            'settings' => ['role_id' => $role->id],
        ]);
        $category = ProcurementCategory::factory()->create(['workflow_reference' => $workflow->code]);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'requester_id' => $requester->id,
            'category_id' => $category->id,
        ]);

        return [$request, $step, $role];
    }
}
