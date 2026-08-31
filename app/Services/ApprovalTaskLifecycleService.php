<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalHistory;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\User;
use App\Models\UserAssignment;
use App\Notifications\ApprovalTaskAssigned;
use App\Notifications\ApprovalTaskEscalated;
use App\Notifications\ApprovalTaskSlaWarning;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

final class ApprovalTaskLifecycleService
{
    /** @return Builder<ApprovalInstanceStep> */
    public function pendingTasks(?User $user = null): Builder
    {
        $user ??= auth()->user();

        $query = ApprovalInstanceStep::query()
            ->with([
                'approvalInstance.purchaseRequest' => fn ($query) => $query->withoutGlobalScopes(),
                'approvalInstance.purchaseRequest.requester',
                'approvalInstance.purchaseRequest.office',
                'approvalInstance.purchaseRequest.branch',
                'approvalInstance.purchaseRequest.department',
                'approvalInstance.purchaseRequest.items',
                'approvalInstance.purchaseRequest.attachments',
                'histories.actor',
            ])
            ->where('status', 'pending')
            ->whereHas('approvalInstance', fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'in_progress']))
            ->whereHas('approvalInstance.purchaseRequest', fn ($query) => $query->withoutGlobalScopes());

        if (! $user instanceof User) {
            return $query->whereKey(0);
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('approver_id', $user->getKey())
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->whereExists(function ($subquery) use ($user): void {
                        $subquery->selectRaw('1')
                            ->from('approver_delegations')
                            ->whereColumn('approver_delegations.delegator_id', 'approval_instance_steps.approver_id')
                            ->where('approver_delegations.delegate_id', $user->getKey())
                            ->where('approver_delegations.is_active', true)
                            ->whereDate('approver_delegations.valid_from', '<=', Carbon::today())
                            ->whereDate('approver_delegations.valid_until', '>=', Carbon::today());
                    });
                });
        });
    }

    public function notifyAssignments(ApprovalInstance $instance): int
    {
        $count = 0;

        foreach ($instance->steps()->where('status', 'pending')->with(['approver', 'approvalInstance.purchaseRequest'])->get() as $step) {
            if (! $step->approver instanceof User) {
                continue;
            }

            Notification::send($step->approver, new ApprovalTaskAssigned($step));
            $count++;
        }

        return $count;
    }

    /**
     * @return array{warnings: int, expired: int, escalated: int}
     */
    public function processSla(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $result = ['warnings' => 0, 'expired' => 0, 'escalated' => 0];

        $warningTasks = ApprovalInstanceStep::query()
            ->where('status', 'pending')
            ->whereNull('sla_warning_sent_at')
            ->where(function (Builder $query) use ($now): void {
                $query->where('sla_warning_at', '<=', $now)
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query->whereNull('sla_warning_at')
                            ->whereNotNull('due_at')
                            ->where('due_at', '<=', $now->copy()->subMinutes(1));
                    });
            })
            ->with(['approver', 'approvalInstance.purchaseRequest'])
            ->get();

        foreach ($warningTasks as $task) {
            $task->forceFill(['sla_warning_sent_at' => $now])->save();
            if ($task->approver instanceof User) {
                Notification::send($task->approver, new ApprovalTaskSlaWarning($task));
            }
            $result['warnings']++;
        }

        $expiredTasks = ApprovalInstanceStep::query()
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->with(['approvalInstance.purchaseRequest', 'approver'])
            ->get();

        foreach ($expiredTasks as $task) {
            $task->forceFill([
                'status' => 'expired',
                'expired_at' => $now,
                'completed_at' => $now,
                'note' => 'Approval task expired after its SLA deadline.',
            ])->save();
            $this->recordHistory($task, 'expired', $task->note, $now);
            $result['expired']++;

            $escalated = $this->escalate($task, $now);
            if ($escalated instanceof ApprovalInstanceStep) {
                $task->forceFill(['escalated_at' => $now])->save();
                $this->recordHistory($task, 'escalated', 'Approval task escalated to a fallback approver.', $now, [
                    'escalated_to_user_id' => $escalated->approver_id,
                    'replacement_step_id' => $escalated->getKey(),
                ]);
                $escalated->load(['approver', 'approvalInstance.purchaseRequest']);
                if ($escalated->approver instanceof User) {
                    Notification::send($escalated->approver, new ApprovalTaskEscalated($task, $escalated->approver_id));
                }
                $result['escalated']++;
            }
        }

        return $result;
    }

    private function escalate(ApprovalInstanceStep $task, Carbon $now): ?ApprovalInstanceStep
    {
        $settings = is_array($task->context) ? $task->context : [];
        $target = $this->fallbackUser($task, $settings);

        if (! $target instanceof User) {
            return null;
        }

        $assignedAt = $now;
        $replacement = $task->approvalInstance->steps()->create([
            'step_order' => $task->step_order,
            'step_key' => $task->step_key.':escalated:'.$task->getKey(),
            'label' => $task->label.' (escalated)',
            'resolver_type' => $task->resolver_type,
            'approval_mode' => $task->approval_mode,
            'step_type' => $task->step_type,
            'is_required' => $task->is_required,
            'sla_minutes' => $task->sla_minutes,
            'escalation_type' => $task->escalation_type,
            'approver_id' => $target->getKey(),
            'original_approver_id' => $task->original_approver_id ?? $task->approver_id,
            'approver_name' => $target->name,
            'approver_role' => $this->assignmentFor($target, $task)?->assignedRole?->name ?? $task->approver_role,
            'office_id' => $task->office_id,
            'branch_id' => $task->branch_id,
            'department_id' => $task->department_id,
            'status' => 'pending',
            'assigned_at' => $assignedAt,
            'due_at' => $task->sla_minutes !== null ? $assignedAt->copy()->addMinutes($task->sla_minutes) : null,
            'sla_warning_at' => $task->sla_minutes !== null
                ? $assignedAt->copy()->addMinutes(max(1, (int) ($task->sla_minutes / 2)))
                : null,
            'context' => [
                ...$settings,
                'escalated_from_step_id' => $task->getKey(),
                'escalated_from_user_id' => $task->approver_id,
                'escalation_target_user_id' => $target->getKey(),
            ],
        ]);

        return $replacement;
    }

    /** @param array<string, mixed> $settings */
    private function fallbackUser(ApprovalInstanceStep $task, array $settings): ?User
    {
        $settings = [
            ...$settings,
            ...(is_array($settings['workflow_settings'] ?? null) ? $settings['workflow_settings'] : []),
        ];
        $userId = data_get($settings, 'fallback_user_id')
            ?? data_get($settings, 'fallback.user_id')
            ?? data_get($settings, 'escalation.fallback_user_id')
            ?? data_get($settings, 'manager_user_id');
        if (is_numeric($userId)) {
            $user = User::query()->find((int) $userId);
            if ($user instanceof User
                && (int) $user->getKey() !== (int) $task->approver_id
                && $this->assignmentFor($user, $task) instanceof UserAssignment) {
                return $user;
            }
        }

        $roleId = data_get($settings, 'fallback_role_id') ?? data_get($settings, 'escalation.fallback_role_id');
        $assignments = UserAssignment::query()
            ->with(['user', 'assignedRole'])
            ->currentlyActive()
            ->where('user_id', '!=', $task->approver_id)
            ->when(is_numeric($roleId), fn (Builder $query): Builder => $query->where('role_id', (int) $roleId))
            ->when(! is_numeric($roleId), fn (Builder $query): Builder => $query->whereHas('assignedRole', fn (Builder $query): Builder => $query
                ->whereIn('name', ['Manager', 'Approver', 'Manajemen'])))
            ->get()
            ->first(fn (UserAssignment $assignment): bool => ($assignment->branch_id === null || (int) $assignment->branch_id === (int) $task->branch_id)
                && ($assignment->department_id === null || (int) $assignment->department_id === (int) $task->department_id)
                && $assignment->allows(ProcurementPermissions::APPROVE));

        return $assignments?->user;
    }

    private function assignmentFor(User $user, ApprovalInstanceStep $task): ?UserAssignment
    {
        return UserAssignment::query()
            ->with('assignedRole')
            ->currentlyActive()
            ->where('user_id', $user->getKey())
            ->where('office_id', $task->office_id)
            ->get()
            ->first(fn (UserAssignment $assignment): bool => ($assignment->branch_id === null || (int) $assignment->branch_id === (int) $task->branch_id)
                && ($assignment->department_id === null || (int) $assignment->department_id === (int) $task->department_id));
    }

    /** @param array<string, mixed> $context */
    private function recordHistory(
        ApprovalInstanceStep $step,
        string $action,
        string $notes,
        Carbon $actedAt,
        array $context = [],
    ): void {
        ApprovalHistory::create([
            'approval_instance_step_id' => $step->getKey(),
            'user_id' => null,
            'role_id' => $this->assignmentFor($step->approver, $step)?->role_id,
            'office_id' => $step->office_id,
            'branch_id' => $step->branch_id,
            'department_id' => $step->department_id,
            'action' => $action,
            'notes' => $notes,
            'acted_at' => $actedAt,
            'workflow_version' => $step->approvalInstance->workflow_version,
            'context' => $context,
        ]);
    }
}
