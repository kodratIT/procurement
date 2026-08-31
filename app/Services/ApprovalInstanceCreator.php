<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ApprovalInstanceCreator
{
    /**
     * @param  array{reference: string, version?: int, context?: array<string, mixed>, steps: list<array<string, mixed>>}  $resolution
     */
    public function create(PurchaseRequest $request, User $submitter, array $resolution): ApprovalInstance
    {
        $steps = $resolution['steps'] ?? [];

        if ($steps === []) {
            throw new InvalidArgumentException('An approval workflow must contain at least one step.');
        }

        $instance = ApprovalInstance::create([
            'purchase_request_id' => $request->getKey(),
            'workflow_reference' => $resolution['reference'],
            'workflow_version' => (int) ($resolution['version'] ?? 1),
            'status' => 'pending',
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $submitter->getKey(),
            'office_id' => $request->office_id,
            'branch_id' => $request->branch_id,
            'department_id' => $request->department_id,
            'cost_center_id' => $request->cost_center_id,
            'submitted_at' => Carbon::now(),
            'context' => $resolution['context'] ?? [],
        ]);

        foreach (array_values($steps) as $index => $step) {
            $instance->steps()->create([
                'step_order' => (int) ($step['step_order'] ?? $index + 1),
                'step_key' => (string) ($step['step_key'] ?? 'approval_'.($index + 1)),
                'label' => (string) ($step['label'] ?? 'Approval'),
                'resolver_type' => (string) ($step['resolver_type'] ?? 'custom'),
                'approver_id' => $step['approver_id'] ?? null,
                'approver_name' => $step['approver_name'] ?? null,
                'approver_role' => $step['approver_role'] ?? null,
                'office_id' => $step['office_id'] ?? $request->office_id,
                'branch_id' => $step['branch_id'] ?? $request->branch_id,
                'department_id' => $step['department_id'] ?? $request->department_id,
                'status' => 'pending',
                'context' => $step['context'] ?? [],
            ]);
        }

        return $instance->load('steps');
    }
}
