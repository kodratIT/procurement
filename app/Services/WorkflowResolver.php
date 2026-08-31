<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Workflow;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a workflow into approver snapshots at submission time.
 *
 * The resolver is intentionally replaceable in the container. E7 can bind a
 * resolver that evaluates workflow versions and conditions without changing
 * the submission transaction or its persistence contract.
 */
class WorkflowResolver
{
    /**
     * @return array{reference: string, version: int, context: array<string, mixed>, steps: list<array<string, mixed>>}
     */
    public function resolve(PurchaseRequest $request, User $submitter): array
    {
        $reference = $request->category?->workflow_reference;
        $configuredWorkflow = is_string($reference) && $reference !== ''
            ? Workflow::query()->where('code', $reference)->where('is_active', true)->first()
            : null;
        $activeVersion = $configuredWorkflow?->activeVersion();

        if ($activeVersion !== null) {
            $reference = $configuredWorkflow->code;
        }

        if (! is_string($reference) || $reference === '') {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow is configured for this procurement category.',
            ]);
        }

        $assignments = UserAssignment::query()
            ->currentlyActive()
            ->withPermission(ProcurementPermissions::APPROVE)
            ->where('office_id', $request->office_id)
            ->where('user_id', '!=', $submitter->getKey())
            ->where(function (Builder $query) use ($request): void {
                $query->whereNull('branch_id');

                if ($request->branch_id !== null) {
                    $query->orWhere('branch_id', $request->branch_id);
                }
            })
            ->where(function (Builder $query) use ($request): void {
                $query->whereNull('department_id');

                if ($request->department_id !== null) {
                    $query->orWhere('department_id', $request->department_id);
                }
            })
            ->with(['user', 'assignedRole'])
            ->get()
            ->sortBy([
                ['branch_id', 'desc'],
                ['department_id', 'desc'],
                ['id', 'asc'],
            ])
            ->values();

        $assignment = $assignments->first();

        if (! $assignment instanceof UserAssignment || $assignment->user === null) {
            throw ValidationException::withMessages([
                'workflow' => 'No eligible approver is configured for this purchase request scope. Contact procurement administration.',
            ]);
        }

        return [
            'reference' => $reference,
            'version' => $activeVersion?->version_number ?? 1,
            'context' => [
                'requester_id' => $request->requester_id,
                'office_id' => $request->office_id,
                'branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
                'cost_center_id' => $request->cost_center_id,
            ],
            'steps' => [[
                'step_order' => 1,
                'step_key' => 'procurement_review',
                'label' => 'Procurement Review',
                'resolver_type' => 'role_in_request_office',
                'approver_id' => $assignment->user_id,
                'approver_name' => $assignment->user->name,
                'approver_role' => $assignment->assignedRole?->name ?? $assignment->role,
                'office_id' => $assignment->office_id,
                'branch_id' => $assignment->branch_id,
                'department_id' => $assignment->department_id,
                'context' => [
                    'assignment_id' => $assignment->getKey(),
                    'role_id' => $assignment->role_id,
                    'office_id' => $assignment->office_id,
                    'branch_id' => $assignment->branch_id,
                    'department_id' => $assignment->department_id,
                ],
            ]],
        ];
    }
}
