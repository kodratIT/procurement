<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ApprovalActionService;
use App\Services\ApprovalInstanceCreator;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApprovalModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_approval_opens_next_step_and_completes_the_purchase_request(): void
    {
        [$request, $instance, $first, $second] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
            ['approver' => 'second', 'order' => 2, 'mode' => 'sequential', 'status' => 'queued'],
        ]);

        app(ApprovalActionService::class)->approve($first, $first->approver, 'Checked.');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::PendingApproval->value, $request->fresh()->status);

        app(ApprovalActionService::class)->approve($second, $second->approver, 'Approved.');

        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::Approved->value, $request->fresh()->status);
        $this->assertDatabaseCount('approval_histories', 2);
    }

    public function test_parallel_all_waits_for_every_approver_before_advancing(): void
    {
        [$request, $instance, $first, $second] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'parallel_all', 'status' => 'pending'],
            ['approver' => 'second', 'order' => 1, 'mode' => 'parallel_all', 'status' => 'pending'],
        ]);

        app(ApprovalActionService::class)->approve($first, $first->approver, 'Approved.');

        $this->assertSame('in_progress', $instance->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::PendingApproval->value, $request->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);

        app(ApprovalActionService::class)->approve($second, $second->approver, 'Approved.');

        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::Approved->value, $request->fresh()->status);
    }

    public function test_parallel_any_skips_siblings_and_completes_on_first_approval(): void
    {
        [$request, $instance, $first, $second] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'parallel_any', 'status' => 'pending'],
            ['approver' => 'second', 'order' => 1, 'mode' => 'parallel_any', 'status' => 'pending'],
        ]);

        app(ApprovalActionService::class)->approve($first, $first->approver, 'Approved.');

        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::Approved->value, $request->fresh()->status);
        $this->assertSame('skipped', $second->fresh()->status);
        $this->assertSame('Skipped because another parallel approver approved this step.', $second->fresh()->note);
    }

    public function test_reject_and_return_require_notes_and_record_terminal_transition(): void
    {
        [$request, $instance, $first] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
        ]);

        try {
            app(ApprovalActionService::class)->reject($first, $first->approver);
            $this->fail('Reject without notes must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('notes', $exception->errors());
        }

        app(ApprovalActionService::class)->returnStep($first, $first->approver, 'Please correct the quantity.');

        $this->assertSame('returned', $instance->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::Returned->value, $request->fresh()->status);
        $this->assertDatabaseHas('approval_histories', ['action' => 'return', 'notes' => 'Please correct the quantity.']);
    }

    public function test_unauthorized_decision_does_not_change_state(): void
    {
        [$request, $instance, $first] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
        ]);
        $intruder = User::factory()->create();
        $this->assign($intruder, $request->office);

        try {
            app(ApprovalActionService::class)->approve($first, $intruder, 'No.');
            $this->fail('An unauthorized approver must fail.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('assigned to another user', $exception->getMessage());
        }

        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::PendingApproval->value, $request->fresh()->status);
        $this->assertSame('pending', $instance->fresh()->status);
    }

    public function test_self_approval_is_rejected(): void
    {
        [$request, $instance, $first] = $this->workflow([
            ['approver' => 'requester', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
        ], requesterIsFirstApprover: true);

        try {
            app(ApprovalActionService::class)->approve($first, $first->approver, 'Self approval.');
            $this->fail('Self approval must fail.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('own purchase request', $exception->getMessage());
        }

        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(PurchaseRequestStatus::PendingApproval->value, $request->fresh()->status);
        $this->assertSame('pending', $instance->fresh()->status);
    }

    public function test_delegated_approval_is_allowed_and_audited(): void
    {
        [$request, $instance, $first] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
        ]);
        $delegator = $first->approver;
        $delegate = User::factory()->create();
        $this->assign($delegate, $request->office);
        ApproverDelegation::factory()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        app(ApprovalActionService::class)->approve($first, $delegate, 'Delegated approval.');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertTrue((bool) $first->fresh()->histories->first()->context['delegated']);
    }

    public function test_non_matching_conditional_step_is_persisted_as_skipped_with_reason(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $this->assign($approver, $office);
        $request = PurchaseRequest::factory()->create([
            'requester_id' => $submitter->id,
            'office_id' => $office->id,
            'status' => PurchaseRequestStatus::ProcurementReview->value,
        ]);

        $instance = app(ApprovalInstanceCreator::class)->create($request, $submitter, [
            'reference' => 'conditional-workflow',
            'version' => 2,
            'steps' => [
                [
                    'step_order' => 1,
                    'label' => 'High value manager',
                    'applicable' => false,
                    'status' => 'skipped',
                    'skip_reason' => 'Amount condition was not met.',
                    'conditions' => [['field_key' => 'total_amount', 'operator' => 'greater_than', 'value' => ['5000']]],
                ],
                [
                    'step_order' => 2,
                    'label' => 'Manager approval',
                    'approver_id' => $approver->id,
                    'approver_name' => $approver->name,
                    'approver_role' => 'Manager',
                    'applicable' => true,
                ],
            ],
        ]);

        $skipped = $instance->steps()->where('step_order', 1)->firstOrFail();

        $this->assertSame('skipped', $skipped->status);
        $this->assertSame('Amount condition was not met.', $skipped->context['skip_reason']);
        $this->assertSame('pending', $instance->steps()->where('step_order', 2)->firstOrFail()->status);
    }

    public function test_duplicate_decision_is_rejected_without_new_history(): void
    {
        [$request, $instance, $first] = $this->workflow([
            ['approver' => 'first', 'order' => 1, 'mode' => 'sequential', 'status' => 'pending'],
        ]);

        app(ApprovalActionService::class)->approve($first, $first->approver, 'Approved.');
        $historyCount = $first->fresh()->histories()->count();

        try {
            app(ApprovalActionService::class)->approve($first->fresh(), $first->approver, 'Duplicate.');
            $this->fail('A duplicate decision must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }

        $this->assertSame($historyCount, $first->fresh()->histories()->count());
        $this->assertSame(PurchaseRequestStatus::Approved->value, $request->fresh()->status);
        $this->assertSame('approved', $instance->fresh()->status);
    }

    /** @return array{PurchaseRequest, ApprovalInstance, ApprovalInstanceStep, ApprovalInstanceStep|null} */
    private function workflow(array $steps, bool $requesterIsFirstApprover = false): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $requester = User::factory()->create();
        $request = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'office_id' => $office->id,
            'status' => PurchaseRequestStatus::PendingApproval->value,
        ]);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'requester_id' => $requester->id,
            'submitted_by_id' => $requester->id,
            'office_id' => $office->id,
            'status' => 'pending',
            'workflow_version' => 3,
        ]);
        $created = [];

        foreach ($steps as $definition) {
            $approver = $definition['approver'] === 'requester' && $requesterIsFirstApprover
                ? $requester
                : User::factory()->create();
            $this->assign($approver, $office);
            $created[] = ApprovalInstanceStep::factory()->create([
                'approval_instance_id' => $instance->id,
                'step_order' => $definition['order'],
                'approval_mode' => $definition['mode'],
                'approver_id' => $approver->id,
                'original_approver_id' => $approver->id,
                'approver_name' => $approver->name,
                'approver_role' => 'Manager',
                'office_id' => $office->id,
                'status' => $definition['status'],
                'assigned_at' => $definition['status'] === 'pending' ? now() : null,
            ]);
        }

        return [$request, $instance, $created[0], $created[1] ?? null];
    }

    private function assign(User $user, Office $office): UserAssignment
    {
        $role = Role::query()->where('name', 'Manager')->firstOrFail();

        return UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => Carbon::today()->subDay(),
            'is_primary' => true,
        ]);
    }
}
