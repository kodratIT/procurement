<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Attachment;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationRecommendation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\FinanceApprovalDetailQuery;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class FinanceApprovalDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_detail_projects_all_evidence_and_workflow_snapshot(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        [$task, $finance] = $this->financeTask();
        $request = PurchaseRequest::withoutGlobalScopes()->findOrFail($task->approvalInstance->purchase_request_id);
        $quotation = Quotation::factory()->create(['purchase_request_id' => $request->id]);
        QuotationRecommendation::factory()->create([
            'purchase_request_id' => $request->id,
            'quotation_id' => $quotation->id,
            'office_id' => $request->office_id,
        ]);
        Attachment::create([
            'attachable_type' => PurchaseRequest::class,
            'attachable_id' => $request->id,
            'uploader_id' => $finance->id,
            'path' => 'private/pr/quote.pdf',
            'original_name' => 'quote.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'collection' => 'default',
            'disk' => 'attachments',
        ]);
        $request->forceFill(['total_amount' => '250000.00'])->save();

        $detail = app(FinanceApprovalDetailQuery::class)->for($task, $finance);

        $this->assertTrue($detail->purchaseRequest->is($request));
        $this->assertCount(1, $detail->quotations);
        $this->assertCount(1, $detail->recommendations);
        $this->assertCount(1, $detail->attachments);
        $this->assertSame('finance', $detail->workflowSnapshot['steps'][0]['step_key']);
        $this->assertSame('250000.00', $detail->budgetContext['amount']);
    }

    /** @return array{ApprovalInstanceStep, User} */
    private function financeTask(): array
    {
        $office = Office::factory()->create();
        $requester = User::factory()->create();
        $finance = User::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
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
            'requester_id' => $requester->id,
            'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
            'total_amount' => '250000.00',
        ]);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'requester_id' => $requester->id,
            'submitted_by_id' => $requester->id,
            'office_id' => $office->id,
            'status' => 'in_progress',
            'context' => [
                'budget_owner_office_id' => $office->id,
                'workflow_snapshot' => ['reference' => 'standard-procurement', 'version' => 1, 'steps' => [['step_key' => 'finance']]],
            ],
        ]);
        $task = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_key' => 'finance',
            'label' => 'Finance Approval',
            'approver_id' => $finance->id,
            'approver_role' => 'Keuangan',
            'office_id' => $office->id,
            'status' => 'pending',
            'context' => ['workflow_settings' => ['budget_check' => ['required' => true]]],
        ]);

        return [$task, $finance];
    }
}
