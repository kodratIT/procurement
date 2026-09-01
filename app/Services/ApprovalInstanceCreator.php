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
    public function __construct(private readonly ApprovalTaskLifecycleService $tasks) {}

    /**
     * @param  array{reference: string, version?: int, workflow_version_id?: int|null, context?: array<string, mixed>, steps: list<array<string, mixed>>}  $resolution
     */
    public function create(PurchaseRequest $request, User $submitter, array $resolution): ApprovalInstance
    {
        $steps = array_values($resolution['steps'] ?? []);
        $applicableSteps = array_values(array_filter(
            $steps,
            static fn (array $step): bool => ($step['applicable'] ?? true)
                && ($step['status'] ?? null) !== 'unresolved'
                && (($step['approver_id'] ?? null) !== null || ($step['approvers'] ?? []) !== []),
        ));

        if ($applicableSteps === []) {
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
            'steps' => $steps,
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

        $firstOrder = collect($applicableSteps)->min(
            static fn (array $step): int => (int) ($step['step_order'] ?? PHP_INT_MAX),
        );

        foreach ($steps as $index => $step) {
            $approvers = $this->approvers($step);
            $status = in_array($step['status'] ?? null, ['skipped', 'unresolved'], true)
                ? 'skipped'
                : (($step['applicable'] ?? true) && (int) ($step['step_order'] ?? $index + 1) === $firstOrder
                    ? 'pending'
                    : 'queued');
            $assignedAt = $status === 'pending' ? Carbon::now() : null;
            $dueAt = $assignedAt !== null && ($step['sla_minutes'] ?? null) !== null
                ? $assignedAt->copy()->addMinutes((int) $step['sla_minutes'])
                : null;
            if ($status === 'skipped') {
                $approvers = [null];
            }

            foreach ($approvers as $approverIndex => $approver) {
                $approver = is_array($approver) ? $approver : ['id' => $approver];
                $approverId = $approver['id'] ?? $step['approver_id'] ?? null;
                $stepContext = [
                    ...($step['context'] ?? []),
                    'conditions' => $step['conditions'] ?? [],
                    'skip_reason' => $step['skip_reason'] ?? ($step['reason'] ?? null),
                    'scope_source' => $step['scope_source'] ?? null,
                    'workflow_settings' => $step['settings'] ?? [],
                    'resolver' => [
                        'type' => $step['resolver_type'] ?? 'custom',
                        'scope_source' => $step['scope_source'] ?? null,
                        'mapping_id' => $step['context']['mapping_id'] ?? null,
                    ],
                ];

                $instance->steps()->create([
                    'step_order' => (int) ($step['step_order'] ?? $index + 1),
                    'step_key' => (string) ($step['step_key'] ?? 'approval_'.($index + 1).'_'.$approverIndex),
                    'label' => (string) ($step['label'] ?? 'Approval'),
                    'resolver_type' => (string) ($step['resolver_type'] ?? 'custom'),
                    'approval_mode' => (string) ($step['approval_mode'] ?? 'sequential'),
                    'step_type' => $step['step_type'] ?? null,
                    'is_required' => (bool) ($step['is_required'] ?? true),
                    'sla_minutes' => $step['sla_minutes'] ?? null,
                    'escalation_type' => $step['escalation_type'] ?? null,
                    'approver_id' => $approverId,
                    'original_approver_id' => $step['original_approver_id'] ?? $approver['original_id'] ?? $approverId,
                    'approver_name' => $approver['name'] ?? $step['approver_name'] ?? null,
                    'approver_role' => $approver['role'] ?? $step['approver_role'] ?? null,
                    'office_id' => $approver['office_id'] ?? $step['office_id'] ?? $request->office_id,
                    'branch_id' => $approver['branch_id'] ?? $step['branch_id'] ?? $request->branch_id,
                    'department_id' => $approver['department_id'] ?? $step['department_id'] ?? $request->department_id,
                    'status' => $status,
                    'assigned_at' => $assignedAt,
                    'due_at' => $dueAt,
                    'sla_warning_at' => $dueAt?->copy()->subMinutes(max(1, (int) (($step['sla_minutes'] ?? 0) / 2))),
                    'context' => $stepContext,
                ]);
            }
        }

        $instance->load('steps');
        $this->tasks->notifyAssignments($instance);

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<array<string, mixed>>
     */
    private function approvers(array $step): array
    {
        if (is_array($step['approvers'] ?? null) && $step['approvers'] !== []) {
            return array_values($step['approvers']);
        }

        return [['id' => $step['approver_id'] ?? null, 'name' => $step['approver_name'] ?? null]];
    }
}
