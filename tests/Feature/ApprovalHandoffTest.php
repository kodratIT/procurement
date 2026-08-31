<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\Activity;
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
use App\Services\AccessContextService;
use App\Services\ProcurementReviewService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApprovalHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_required_approver_blocks_handoff_without_instance_or_status_change(): void
    {
        [$request, $reviewer, $office, $step] = $this->reviewedRequest();
        $this->setReviewerContext($reviewer, $office);

        try {
            app(ProcurementReviewService::class)->handoffToApproval($request, $reviewer);
            $this->fail('A missing required approver must block handoff.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Required approver', implode(' ', $exception->errors()['workflow'] ?? []));
        }

        $this->assertSame(PurchaseRequestStatus::ProcurementReview->value, $request->fresh()->status);
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $request->id,
            'event' => 'approval_handoff',
        ]);
        $this->assertNotNull($step->fresh());
    }

    public function test_valid_review_handoff_creates_exactly_one_active_instance_and_first_task(): void
    {
        [$request, $reviewer, $office, $step] = $this->reviewedRequest();
        $manager = Role::query()->where('name', 'Manager')->firstOrFail();
        $approver = User::factory()->create(['name' => 'Office manager']);
        UserAssignment::factory()->create([
            'user_id' => $approver->id,
            'office_id' => $office->id,
            'role_id' => $manager->id,
            'role' => $manager->name,
        ]);
        ApproverMapping::factory()->create([
            'workflow_step_id' => $step->id,
            'resolver_type' => 'role_in_request_office',
            'role_id' => $manager->id,
            'user_id' => null,
            'office_id' => $office->id,
            'scope_source' => 'request_office',
        ]);
        $this->setReviewerContext($reviewer, $office);

        $handedOff = app(ProcurementReviewService::class)->handoffToApproval($request, $reviewer);
        $instance = $handedOff->approvalInstances->firstOrFail();

        $this->assertSame(PurchaseRequestStatus::PendingApproval->value, $handedOff->status);
        $this->assertSame(1, $handedOff->approvalInstances()->whereIn('status', ['pending', 'in_progress'])->count());
        $this->assertSame(1, $handedOff->approvalInstances()->count());
        $this->assertSame('pending', $instance->steps->first()->status);
        $this->assertSame($approver->id, $instance->steps->first()->approver_id);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $request->id,
            'from_status' => PurchaseRequestStatus::ProcurementReview->value,
            'to_status' => PurchaseRequestStatus::PendingApproval->value,
            'event' => 'approval_handoff',
            'decision' => 'handoff',
        ]);
        $this->assertTrue(Activity::query()
            ->where('subject_type', PurchaseRequest::class)
            ->where('subject_id', $request->id)
            ->where('event', 'approval_handoff')
            ->exists());
    }

    /** @return array{PurchaseRequest, User, Office, WorkflowStep} */
    private function reviewedRequest(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create(['name' => 'Source office']);
        $reviewer = User::factory()->create();
        $this->assign($reviewer, $office, 'Pengadaan');
        $requester = User::factory()->create();
        $workflow = Workflow::create(['code' => 'review-handoff', 'name' => 'Review handoff workflow']);
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
            'settings' => ['role' => 'Manager'],
        ]);
        $category = ProcurementCategory::factory()->create(['workflow_reference' => $workflow->code]);
        $request = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'office_id' => $office->id,
            'category_id' => $category->id,
            'status' => PurchaseRequestStatus::ProcurementReview->value,
            'priority' => 'normal',
        ]);

        return [$request, $reviewer, $office, $step];
    }

    private function assign(User $user, Office $office, string $roleName): UserAssignment
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
    }

    private function setReviewerContext(User $reviewer, Office $office): void
    {
        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($reviewer->assignments()->where('office_id', $office->id)->firstOrFail());
    }
}
