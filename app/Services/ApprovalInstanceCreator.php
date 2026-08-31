<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ApprovalInstanceCreator
{
    /**
     * @param  array{reference: string, version?: int, workflow_version_id?: int|null, context?: array<string, mixed>, steps: list<array<string, mixed>>}  $resolution
     */
    public function create(PurchaseRequest $request, User $submitter, array $resolution): ApprovalInstance
    {
        $steps = array_values(array_filter(
            $resolution['steps'] ?? [],
            static fn (array $step): bool => ($step['applicable'] ?? true) && ($step['approver_id'] ?? null) !== null,
        ));

        if ($steps === []) {
            throw ValidationException::withMessages([
                'workflow' => 'An approval workflow must contain at least one resolved step.',
            ]);
        }

        if ($request->approvalInstances()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            throw ValidationException::withMessages([
                'workflow' => 'This purchase request already has an active approval instance.',
            ]);
        }

        $workflowSnapshot = [
            'reference' => $resolution['reference'],
            'version' => (int) ($resolution['version'] ?? 1),
            'workflow_version_id' => $resolution['workflow_version_id'] ?? null,
            'context' => $resolution['context'] ?? [],
        ];
        $instance = ApprovalInstance::create([
            'purchase_request_id' => $request->getKey(),
            'workflow_version_id' => $resolution['workflow_version_id'] ?? null,
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
            'context' => [
                ...($resolution['context'] ?? []),
                'workflow_snapshot' => $workflowSnapshot,
            ],
        ]);

        foreach ($steps as $index => $step) {
            $instance->steps()->create([
                'step_order' => (int) ($step['step_order'] ?? $index + 1),
                'step_key' => (string) ($step['step_key'] ?? 'approval_'.($index + 1)),
                'label' => (string) ($step['label'] ?? 'Approval'),
                'resolver_type' => (string) ($step['resolver_type'] ?? 'custom'),
                'approver_id' => $step['approver_id'],
                'approver_name' => $step['approver_name'] ?? null,
                'approver_role' => $step['approver_role'] ?? null,
                'office_id' => $step['office_id'] ?? $request->office_id,
                'branch_id' => $step['branch_id'] ?? $request->branch_id,
                'department_id' => $step['department_id'] ?? $request->department_id,
                'status' => $index === 0 ? 'pending' : 'queued',
                'context' => [
                    ...($step['context'] ?? []),
                    'conditions' => $step['conditions'] ?? [],
                    'scope_source' => $step['scope_source'] ?? null,
                    'resolver' => [
                        'type' => $step['resolver_type'] ?? 'custom',
                        'scope_source' => $step['scope_source'] ?? null,
                        'mapping_id' => $step['context']['mapping_id'] ?? null,
                    ],
                ],
            ]);
        }

        return $instance->load('steps');
    }
}
