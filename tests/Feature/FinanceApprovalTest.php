<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\BudgetCheck;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ApprovalActionService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FinanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_approve_and_records_scope_role_and_workflow_version(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$task, $finance, $request] = $this->financeTask();
        app()->instance(BudgetCheck::class, new class implements BudgetCheck
        {
            public function check(PurchaseRequest $purchaseRequest): bool
            {
                return true;
            }
        });

        app(ApprovalActionService::class)->approve($task, $finance, 'Budget checked and approved.');

        $this->assertSame(PurchaseRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertDatabaseHas('approval_histories', [
            'approval_instance_step_id' => $task->id,
            'action' => 'approve',
            'user_id' => $finance->id,
            'role_id' => $this->financeRole()->id,
            'office_id' => $request->office_id,
            'workflow_version' => 3,
        ]);
        $history = $task->fresh()->histories->firstOrFail();
        $this->assertSame('procurement.approve', $history->context['permission']);
        $this->assertSame($request->office_id, $history->context['scope']['office_id']);
    }

    public function test_finance_can_reject_or_return_only_with_notes(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$rejectTask, $finance, $rejectRequest] = $this->financeTask();
        app(ApprovalActionService::class)->reject($rejectTask, $finance, 'Quoted amount exceeds the approved budget.');
        $this->assertSame(PurchaseRequest::STATUS_REJECTED, $rejectRequest->fresh()->status);

        [$returnTask, $returnFinance, $returnRequest] = $this->financeTask();
        app(ApprovalActionService::class)->returnStep($returnTask, $returnFinance, 'Please attach the signed quotation.');
        $this->assertSame(PurchaseRequest::STATUS_RETURNED, $returnRequest->fresh()->status);

        [$missingNotesTask, $missingNotesFinance] = $this->financeTask();
        $this->expectException(ValidationException::class);
        app(ApprovalActionService::class)->approve($missingNotesTask, $missingNotesFinance);
    }

    public function test_required_budget_check_blocks_final_approval(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$task, $finance, $request] = $this->financeTask();
        app()->instance(BudgetCheck::class, new class implements BudgetCheck
        {
            public function check(PurchaseRequest $purchaseRequest): bool
            {
                return false;
            }
        });

        try {
            app(ApprovalActionService::class)->approve($task, $finance, 'Approval attempted.');
            $this->fail('A failed required budget check must block approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('budget', $exception->errors());
        }

        $this->assertSame(PurchaseRequest::STATUS_PENDING_APPROVAL, $request->fresh()->status);
    }

    public function test_requester_and_out_of_scope_finance_cannot_decide_task(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$task, $finance, $request] = $this->financeTask();
        $otherOffice = Office::factory()->create();
        $outOfScope = User::factory()->create();
        $role = $this->financeRole();
        UserAssignment::factory()->create([
            'user_id' => $outOfScope->id,
            'office_id' => $otherOffice->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => Carbon::today()->subDay(),
        ]);

        try {
            app(ApprovalActionService::class)->approve($task, $outOfScope, 'Out of scope.');
            $this->fail('An out-of-scope finance user must be denied.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('assigned to another user', $exception->getMessage());
        }

        [$selfTask, $selfFinance, $selfRequest] = $this->financeTask(true);
        try {
            app(ApprovalActionService::class)->approve($selfTask, $selfFinance, 'Self approval.');
            $this->fail('A requester must not approve their own request.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('own purchase request', $exception->getMessage());
        }

        $this->assertSame(PurchaseRequest::STATUS_PENDING_APPROVAL, $request->fresh()->status);
        $this->assertSame(PurchaseRequest::STATUS_PENDING_APPROVAL, $selfRequest->fresh()->status);
    }

    /** @return array{ApprovalInstanceStep, User, PurchaseRequest} */
    private function financeTask(bool $requesterIsFinance = false): array
    {
        $office = Office::factory()->create();
        $requester = User::factory()->create();
        $finance = User::factory()->create();
        $role = $this->financeRole();
        UserAssignment::factory()->create([
            'user_id' => $finance->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => Carbon::today()->subDay(),
            'is_primary' => true,
        ]);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'requester_id' => $requesterIsFinance ? $finance->id : $requester->id,
            'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
            'total_amount' => '500000.00',
        ]);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $request->requester_id,
            'office_id' => $office->id,
            'workflow_version' => 3,
            'status' => 'in_progress',
        ]);
        $task = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_key' => 'finance',
            'label' => 'Finance Approval',
            'step_type' => 'final_approval',
            'approver_id' => $finance->id,
            'approver_role' => $role->name,
            'office_id' => $office->id,
            'status' => 'pending',
            'context' => ['workflow_settings' => ['budget_check' => ['required' => true, 'hook' => 'E8-US01']]],
        ]);

        return [$task, $finance, $request];
    }

    private function financeRole(): Role
    {
        return Role::query()->where('name', 'Keuangan')->firstOrFail();
    }
}
