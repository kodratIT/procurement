<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProcurementReviewResource;
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
use App\Services\WorkflowPreviewService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkflowPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_explains_workflow_conditions_source_budget_and_scope(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$workflow, $step, $category, $requester, $office, $manager] = $this->workflowFixture();
        $approver = User::factory()->create(['name' => 'Office A manager']);
        UserAssignment::factory()->create([
            'user_id' => $approver->id,
            'office_id' => $office->id,
            'role_id' => $manager->id,
            'role' => $manager->name,
        ]);
        $mapping = ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'role_in_request_office',
            'role_id' => $manager->id,
            'user_id' => null,
            'office_id' => $office->id,
            'scope_source' => 'request_office',
        ]);
        $preview = app(WorkflowPreviewService::class)->preview($this->requesterRequest($requester, $category, $office), $requester);

        $this->assertTrue($preview['can_handoff']);
        $this->assertSame($workflow->name, $preview['workflow']['name']);
        $this->assertSame(1, $preview['workflow']['version']);
        $this->assertSame($office->name, $preview['source']['office_name']);
        $this->assertSame($office->name, $preview['budget_owner']['office_name']);
        $this->assertSame('Office A manager', $preview['steps'][0]['approver_name']);
        $this->assertSame('request_office', $preview['steps'][0]['scope_source']);
        $this->assertSame($mapping->id, $preview['steps'][0]['context']['mapping_id']);
    }

    public function test_review_resource_is_visible_to_active_procurement_reviewer(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $reviewer = User::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $this->assignApprover($reviewer, $office, $role);
        $this->actingAs($reviewer);

        $this->assertTrue(ProcurementReviewResource::canViewAny());
    }

    public function test_same_workflow_resolves_different_mappings_per_office(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$workflow, $step, $category, $requester, $officeA, $manager] = $this->workflowFixture();
        $officeB = Office::factory()->create(['name' => 'Office B']);
        $approverA = User::factory()->create(['name' => 'Approver A']);
        $approverB = User::factory()->create(['name' => 'Approver B']);
        $this->assignApprover($approverA, $officeA, $manager);
        $this->assignApprover($approverB, $officeB, $manager);
        $this->mapApprover($step, $officeA, $manager);
        $this->mapApprover($step, $officeB, $manager);

        $requestA = $this->requesterRequest($requester, $category, $officeA);
        $requestB = $this->requesterRequest($requester, $category, $officeB);
        $previewA = app(WorkflowPreviewService::class)->preview($requestA, $requester);
        $previewB = app(WorkflowPreviewService::class)->preview($requestB, $requester);

        $this->assertSame($workflow->name, $previewA['workflow']['name']);
        $this->assertSame($workflow->name, $previewB['workflow']['name']);
        $this->assertSame('Approver A', $previewA['steps'][0]['approver_name']);
        $this->assertSame('Approver B', $previewB['steps'][0]['approver_name']);
        $this->assertSame($officeA->id, $previewA['steps'][0]['context']['office_id']);
        $this->assertSame($officeB->id, $previewB['steps'][0]['context']['office_id']);
    }

    /** @return array{Workflow, WorkflowStep, ProcurementCategory, User, Office, Role} */
    private function workflowFixture(): array
    {
        $office = Office::factory()->create(['name' => 'Office A']);
        $requester = User::factory()->create();
        $manager = Role::query()->where('name', 'Manager')->firstOrFail();
        $workflow = Workflow::create(['code' => 'office-shared', 'name' => 'Shared office workflow']);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);
        $step = $version->steps()->create([
            'sequence' => 1,
            'name' => 'Manager approval',
            'step_type' => 'approval',
            'resolver_type' => 'role_in_request_office',
            'settings' => ['role_id' => $manager->id],
        ]);
        $step->conditions()->create([
            'field_key' => 'priority',
            'operator' => 'in',
            'value' => ['normal', 'high'],
        ]);
        $category = ProcurementCategory::factory()->create(['workflow_reference' => $workflow->code]);

        return [$workflow, $step, $category, $requester, $office, $manager];
    }

    private function requesterRequest(User $requester, ProcurementCategory $category, Office $office): PurchaseRequest
    {
        return PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'category_id' => $category->id,
            'office_id' => $office->id,
            'priority' => 'high',
            'total_amount' => 1000,
        ]);
    }

    private function assignApprover(User $approver, Office $office, Role $role): UserAssignment
    {
        return UserAssignment::factory()->create([
            'user_id' => $approver->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
        ]);
    }

    private function mapApprover(WorkflowStep $step, Office $office, Role $role): ApproverMapping
    {
        return ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'role_in_request_office',
            'role_id' => $role->id,
            'user_id' => null,
            'office_id' => $office->id,
            'scope_source' => 'request_office',
        ]);
    }
}
