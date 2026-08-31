<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\ApprovalInstanceCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApprovalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_persists_one_snapshot_and_only_first_step_is_pending(): void
    {
        $request = PurchaseRequest::factory()->create();
        $submitter = User::factory()->create();
        $approver = User::factory()->create(['name' => 'Snapshot approver']);

        $instance = app(ApprovalInstanceCreator::class)->create($request, $submitter, [
            'reference' => 'shared-workflow',
            'version' => 4,
            'workflow_version_id' => null,
            'context' => ['office_id' => $request->office_id],
            'steps' => [
                [
                    'step_order' => 1,
                    'step_key' => 'manager',
                    'label' => 'Manager approval',
                    'resolver_type' => 'role_in_request_office',
                    'approver_id' => $approver->id,
                    'approver_name' => $approver->name,
                    'approver_role' => 'Manager',
                    'office_id' => $request->office_id,
                    'conditions' => [['field_key' => 'priority', 'operator' => 'equals', 'value' => ['high']]],
                    'context' => ['mapping_id' => 42, 'scope_source' => 'request_office'],
                ],
                [
                    'step_order' => 2,
                    'step_key' => 'finance',
                    'label' => 'Finance approval',
                    'resolver_type' => 'specific_user',
                    'approver_id' => $approver->id,
                    'approver_name' => $approver->name,
                    'approver_role' => 'Finance',
                    'office_id' => $request->office_id,
                    'conditions' => [],
                ],
            ],
        ]);

        $this->assertSame(1, $request->approvalInstances()->count());
        $this->assertSame('shared-workflow', $instance->workflow_reference);
        $this->assertSame(4, $instance->workflow_version);
        $this->assertSame('shared-workflow', $instance->context['workflow_snapshot']['reference']);
        $this->assertSame('pending', $instance->steps[0]->status);
        $this->assertSame('queued', $instance->steps[1]->status);
        $this->assertSame(42, $instance->steps[0]->context['mapping_id']);
        $this->assertSame('high', $instance->steps[0]->context['conditions'][0]['value'][0]);
    }

    public function test_workflow_and_approver_snapshot_cannot_be_changed_or_deleted(): void
    {
        $request = PurchaseRequest::factory()->create();
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'office_id' => $request->office_id,
        ]);
        $step = ApprovalInstanceStep::factory()->create(['approval_instance_id' => $instance->id]);

        try {
            $instance->update(['workflow_reference' => 'changed']);
            $this->fail('The workflow snapshot must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_instance', $exception->errors());
        }

        try {
            $step->update(['approver_id' => User::factory()->create()->id]);
            $this->fail('The approver snapshot must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_step', $exception->errors());
        }

        $instance->refresh()->update(['status' => 'approved']);
        $this->assertSame('approved', $instance->fresh()->status);
    }
}
