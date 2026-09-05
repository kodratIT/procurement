<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BudgetCheck;
use App\Models\ApprovalHistory;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\UserAssignment;
use App\Notifications\ApprovalTaskAssigned;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class ApprovalActionService
{
    private const ACTIONS = ['approve', 'reject', 'return', 'cancel'];

    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly PurchaseRequestTimeline $timeline,
        private readonly ApprovalTaskLifecycleService $tasks,
        private readonly FeatureModuleService $featureModules,
        private readonly WorkflowStageService $workflowStages,
        private readonly ?BudgetCheck $budgetCheck = null,
        private readonly ?BudgetReservationService $budgetReservations = null,
    ) {}

    public function approve(
        ApprovalInstanceStep $step,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->decide($step, 'approve', $actor, $notes, $metadata);
    }

    public function reject(
        ApprovalInstanceStep $step,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->decide($step, 'reject', $actor, $notes, $metadata);
    }

    public function returnStep(
        ApprovalInstanceStep $step,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->decide($step, 'return', $actor, $notes, $metadata);
    }

    public function returnToRequester(
        ApprovalInstanceStep $step,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->returnStep($step, $actor, $notes, $metadata);
    }

    public function cancel(
        ApprovalInstanceStep $step,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->decide($step, 'cancel', $actor, $notes, $metadata);
    }

    /**
     * Apply one approval decision through the sole decision boundary.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function decide(
        ApprovalInstanceStep $step,
        string $action,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        $actor ??= auth()->user();
        $this->validateAction($action, $notes);

        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated approver is required.');
        }

        $this->featureModules?->assertEnabled(FeatureRegistry::FEATURE_APPROVAL_INBOX, $actor);
        if (in_array($step->status, ['approved', 'rejected', 'returned', 'skipped', 'expired'], true)) {
            throw ValidationException::withMessages([
                'approval' => 'This approval task has already been decided.',
            ]);
        }
        $this->authorizeActor($step, $actor);

        if ($action === 'approve') {
            $step->load([
                'approvalInstance.purchaseRequest' => fn ($query) => $query->withoutGlobalScopes(),
            ]);
            $this->assertBudgetCheckPassed($step, $step->approvalInstance->purchaseRequest);
        }

        return $this->transaction->run(
            'record approval decision',
            function () use ($step, $action, $actor, $notes, $metadata): ApprovalInstanceStep {
                $lockedStep = ApprovalInstanceStep::query()
                    ->lockForUpdate()
                    ->with([
                        'approvalInstance.purchaseRequest' => fn ($query) => $query->withoutGlobalScopes(),
                        'approvalInstance.steps',
                    ])
                    ->findOrFail($step->getKey());
                $instance = $lockedStep->approvalInstance;
                $request = $instance->purchaseRequest;

                if (! $instance->isActive()) {
                    throw ValidationException::withMessages([
                        'approval' => 'This approval instance is no longer active.',
                    ]);
                }

                if ($lockedStep->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'approval' => 'This approval task has already been decided.',
                    ]);
                }

                $this->authorizeActor($lockedStep, $actor);
                $this->assertActionable($lockedStep, $instance->steps);

                $actedAt = Carbon::now();
                $lockedStep->forceFill([
                    'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'returned'),
                    'decision' => $action,
                    'note' => $notes,
                    'acted_by_id' => $actor->getKey(),
                    'acted_at' => $actedAt,
                    'completed_at' => $actedAt,
                ])->save();

                $this->recordHistory($lockedStep, $actor, $action, $notes, $actedAt, $metadata);
                $instance->load('steps');

                if ($action !== 'approve') {
                    $this->closeAsTerminal($instance, $request, $actor, $action, $notes);
                } elseif ($this->groupCompleted($lockedStep, $instance->steps)) {
                    $this->advance($instance, $request, $actor, $lockedStep);
                } else {
                    $instance->forceFill(['status' => 'in_progress'])->save();
                }

                return $lockedStep->fresh(['approvalInstance', 'histories']);
            },
            [
                'approval_instance_step_id' => $step->getKey(),
                'approval_instance_id' => $step->approval_instance_id,
                'actor_id' => $actor->getKey(),
            ],
        );
    }

    public function handle(
        ApprovalInstanceStep $step,
        string $action,
        ?User $actor = null,
        ?string $notes = null,
        array $metadata = [],
    ): ApprovalInstanceStep {
        return $this->decide($step, $action, $actor, $notes, $metadata);
    }

    /** @return array{warnings: int, expired: int, escalated: int} */
    public function processSla(?Carbon $now = null): array
    {
        return $this->tasks->processSla($now);
    }

    /**
     * @param  Collection<int, ApprovalInstanceStep>  $steps
     */
    private function assertActionable(ApprovalInstanceStep $step, Collection $steps): void
    {
        $openOrders = $steps
            ->whereIn('status', ['pending', 'queued'])
            ->pluck('step_order')
            ->unique()
            ->sort()
            ->values();
        $firstOrder = $openOrders->first();
        if ($firstOrder !== $step->step_order) {
            throw ValidationException::withMessages([
                'approval' => 'This approval step is not open yet.',
            ]);
        }
    }

    /**
     * @param  Collection<int, ApprovalInstanceStep>  $steps
     */
    private function groupCompleted(ApprovalInstanceStep $step, Collection $steps): bool
    {
        $group = $steps->where('step_order', $step->step_order);
        $mode = (string) ($step->approval_mode ?: data_get($step->context, 'approval_mode', 'sequential'));

        if ($mode === 'parallel_any') {
            foreach ($group->where('status', 'pending')->where('id', '!=', $step->getKey()) as $sibling) {
                $reason = 'Skipped because another parallel approver approved this step.';
                $sibling->forceFill([
                    'status' => 'skipped',
                    'note' => $reason,
                    'completed_at' => Carbon::now(),
                ])->save();
                $this->recordHistory($sibling, $step->actedBy, 'skipped', $reason, Carbon::now(), [
                    'source_step_id' => $step->getKey(),
                ]);
            }

            return true;
        }

        if ($mode === 'parallel_all') {
            return $group->every(fn (ApprovalInstanceStep $candidate): bool => $candidate->status === 'approved');
        }

        return true;
    }

    private function advance(
        ApprovalInstance $instance,
        PurchaseRequest $request,
        User $actor,
        ApprovalInstanceStep $completedStep,
    ): void {
        $steps = $instance->steps()->get();
        $next = $steps
            ->whereIn('status', ['pending', 'queued'])
            ->sortBy(['step_order', 'id'])
            ->first();

        if (! $next instanceof ApprovalInstanceStep) {
            $this->setTerminalStatus(
                $instance,
                $request,
                $actor,
                PurchaseRequest::STATUS_APPROVED,
                'approved',
                "Seluruh tahap persetujuan telah selesai. PR {$request->pr_number} telah disetujui.",
            );

            return;
        }

        $nextOrder = $next->step_order;
        $nextGroup = $steps->where('step_order', $nextOrder)->whereIn('status', ['pending', 'queued']);
        $assignedAt = Carbon::now();

        foreach ($nextGroup as $task) {
            if ($task->status !== 'pending') {
                $task->forceFill([
                    'status' => 'pending',
                    'assigned_at' => $assignedAt,
                    'due_at' => $task->sla_minutes !== null ? $assignedAt->copy()->addMinutes($task->sla_minutes) : null,
                    'sla_warning_at' => $task->sla_minutes !== null
                        ? $assignedAt->copy()->addMinutes(max(1, (int) ($task->sla_minutes / 2)))
                        : null,
                ])->save();
                $task->loadMissing(['approver', 'approvalInstance.purchaseRequest']);
                if ($task->approver instanceof User) {
                    Notification::send($task->approver, new ApprovalTaskAssigned($task));
                }
            }
        }

        $instance->forceFill(['status' => 'in_progress'])->save();

        $nextStepKey = $next->step_key;
        if (is_string($nextStepKey) && $nextStepKey !== '' && $request->status !== $nextStepKey) {
            $fromStatus = $request->status;
            PurchaseRequest::query()->withoutGlobalScopes()->whereKey($request->getKey())->update([
                'status' => $nextStepKey,
                'updated_at' => now(),
            ]);
            $request->status = $nextStepKey;
            $this->timeline->record(
                $request,
                $actor,
                $fromStatus,
                $nextStepKey,
                'approval_advance',
                'advance',
                "Tahap {$completedStep->label} disetujui oleh {$actor->name}. PR dilanjutkan ke tahap {$next->label} dan menunggu persetujuan {$next->approver_name}.",
                ['approval_instance_id' => $instance->getKey(), 'next_step_key' => $nextStepKey],
            );
        }
    }

    private function closeAsTerminal(
        ApprovalInstance $instance,
        PurchaseRequest $request,
        User $actor,
        string $action,
        ?string $notes,
    ): void {
        $status = match ($action) {
            'reject' => PurchaseRequest::STATUS_REJECTED,
            'cancel' => PurchaseRequest::STATUS_CANCELLED,
            default => PurchaseRequest::STATUS_RETURNED,
        };
        $reason = match ($action) {
            'reject' => 'PR ditolak dan proses persetujuan dihentikan.',
            'cancel' => 'PR dibatalkan sebelum proses persetujuan selesai.',
            default => 'PR dikembalikan kepada pengaju untuk diperbaiki.',
        };

        foreach ($instance->steps()->whereIn('status', ['pending', 'queued'])->get() as $task) {
            $skipReason = 'Skipped because the approval workflow reached a terminal decision.';
            $task->forceFill([
                'status' => 'skipped',
                'note' => $skipReason,
                'completed_at' => Carbon::now(),
            ])->save();
            $this->recordHistory($task, $actor, 'skipped', $skipReason, Carbon::now(), [
                'source_action' => $action,
            ]);
        }

        $this->setTerminalStatus($instance, $request, $actor, $status, $action, $notes ?: $reason);
    }

    private function setTerminalStatus(
        ApprovalInstance $instance,
        PurchaseRequest $request,
        User $actor,
        string $requestStatus,
        string $decision,
        string $note,
    ): void {
        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_APPROVAL_INBOX, $actor);

        if ($requestStatus === PurchaseRequest::STATUS_APPROVED
            && $request->cost_center_id !== null) {
            $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_BUDGETS, $actor);
            if (! $this->budgetReservations instanceof BudgetReservationService) {
                throw new \RuntimeException('Budget reservation service is required for approved cost-centred requests.');
            }
        }

        $fromStatus = $request->status;
        $instanceStatus = match ($requestStatus) {
            PurchaseRequest::STATUS_APPROVED => 'approved',
            PurchaseRequest::STATUS_REJECTED => 'rejected',
            PurchaseRequest::STATUS_CANCELLED => 'cancelled',
            default => 'returned',
        };
        $instance->forceFill(['status' => $instanceStatus])->save();
        PurchaseRequest::query()->withoutGlobalScopes()->whereKey($request->getKey())->update([
            'status' => $requestStatus,
            'updated_at' => now(),
        ]);
        $request->status = $requestStatus;
        if ($requestStatus === PurchaseRequest::STATUS_APPROVED
            && $request->cost_center_id !== null
            && $this->budgetReservations instanceof BudgetReservationService) {
            $this->budgetReservations->reserve($request, actor: $actor);
        }
        $this->timeline->record(
            $request,
            $actor,
            $fromStatus,
            $requestStatus,
            'approval_decision',
            $decision,
            $note,
            ['approval_instance_id' => $instance->getKey(), 'workflow_version' => $instance->workflow_version],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordHistory(
        ApprovalInstanceStep $step,
        User $actor,
        string $action,
        ?string $notes,
        Carbon $actedAt,
        array $metadata,
    ): ApprovalHistory {
        $assignment = $this->assignmentForActor($step, $actor);

        return $step->histories()->create([
            'user_id' => $actor->getKey(),
            'role_id' => $assignment?->role_id,
            'office_id' => $assignment?->office_id ?? $step->office_id,
            'branch_id' => $assignment?->branch_id ?? $step->branch_id,
            'department_id' => $assignment?->department_id ?? $step->department_id,
            'action' => $action,
            'notes' => $notes,
            'acted_at' => $actedAt,
            'workflow_version' => $step->approvalInstance->workflow_version,
            'ip_address' => $metadata['ip_address'] ?? (app()->bound('request') ? request()->ip() : null),
            'device' => $metadata['device'] ?? (app()->bound('request') ? request()->userAgent() : null),
            'context' => [
                ...$metadata,
                'permission' => ProcurementPermissions::APPROVE,
                'scope' => [
                    'office_id' => $assignment?->office_id ?? $step->office_id,
                    'branch_id' => $assignment?->branch_id ?? $step->branch_id,
                    'department_id' => $assignment?->department_id ?? $step->department_id,
                ],
                'actor_role' => $assignment?->assignedRole?->name ?? $step->approver_role,
                'delegated' => $this->isDelegated($step, $actor),
            ],
        ]);
    }

    private function assertBudgetCheckPassed(ApprovalInstanceStep $step, PurchaseRequest $request): void
    {
        $budgetCheck = data_get($step->context, 'workflow_settings.budget_check', data_get($step->context, 'budget_check', []));
        if (! is_array($budgetCheck) || ! (bool) ($budgetCheck['required'] ?? false)) {
            return;
        }

        if (! $this->budgetCheck instanceof BudgetCheck || ! $this->budgetCheck->check($request)) {
            throw ValidationException::withMessages([
                'budget' => 'The required budget check must pass before final approval.',
            ]);
        }
    }

    private function authorizeActor(ApprovalInstanceStep $step, User $actor): void
    {
        if (! $actor->is_active) {
            throw new AuthorizationException('An inactive user cannot decide approval tasks.');
        }

        $allowSelfApproval = (bool) data_get($step->context, 'allow_self_approval', false)
            || (bool) data_get($step->context, 'resolver.allow_self_approval', false);
        if (! $allowSelfApproval && (int) $actor->getKey() === (int) $step->approvalInstance->requester_id) {
            throw new AuthorizationException('A requester cannot approve their own purchase request.');
        }

        $assigned = (int) $actor->getKey() === (int) $step->approver_id;
        $delegated = $this->isDelegated($step, $actor);
        if (! $assigned && ! $delegated) {
            throw new AuthorizationException('This approval task is assigned to another user.');
        }

        $assignments = UserAssignment::query()
            ->with('assignedRole')
            ->currentlyActive()
            ->where('user_id', $actor->getKey())
            ->where('office_id', $step->office_id)
            ->get()
            ->filter(fn (UserAssignment $assignment): bool => ($assignment->branch_id === null || (int) $assignment->branch_id === (int) $step->branch_id)
                && ($assignment->department_id === null || (int) $assignment->department_id === (int) $step->department_id));

        if ($assignments->isNotEmpty() && $assignments->contains(fn (UserAssignment $assignment): bool => $assignment->allows(ProcurementPermissions::APPROVE))) {
            return;
        }

        if ($assignments->isEmpty() && $actor->can(ProcurementPermissions::APPROVE)) {
            return;
        }

        throw new AuthorizationException('The active assignment does not authorize this approval action.');
    }

    private function assignmentForActor(ApprovalInstanceStep $step, User $actor): ?UserAssignment
    {
        return UserAssignment::query()
            ->with('assignedRole')
            ->currentlyActive()
            ->where('user_id', $actor->getKey())
            ->where('office_id', $step->office_id)
            ->get()
            ->first(fn (UserAssignment $assignment): bool => ($assignment->branch_id === null || (int) $assignment->branch_id === (int) $step->branch_id)
                && ($assignment->department_id === null || (int) $assignment->department_id === (int) $step->department_id));
    }

    private function isDelegated(ApprovalInstanceStep $step, User $actor): bool
    {
        if ((int) $actor->getKey() === (int) $step->approver_id) {
            return false;
        }

        return ApproverDelegation::query()
            ->activeAt()
            ->where('delegator_id', $step->approver_id)
            ->where('delegate_id', $actor->getKey())
            ->exists()
            || (int) data_get($step->context, 'delegated_from_user_id') === (int) $step->approver_id
            && (int) data_get($step->context, 'delegation_id') > 0
            && (int) data_get($step->context, 'delegate_id', $actor->getKey()) === (int) $actor->getKey();
    }

    private function validateAction(string $action, ?string $notes): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages(['action' => 'The approval action is invalid.']);
        }

        if (blank($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required for every approval decision.',
            ]);
        }
    }
}
