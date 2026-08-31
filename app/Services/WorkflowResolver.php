<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkflowConditionOperator;
use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves workflow steps into approver snapshots at submission time.
 */
class WorkflowResolver
{
    /**
     * @return array{reference: string, version: int, workflow_version_id: int|null, context: array<string, mixed>, steps: list<array<string, mixed>>, missing_approvers?: list<array<string, mixed>>}
     */
    public function resolve(PurchaseRequest $request, User $submitter, ?Workflow $workflow = null): array
    {
        return $this->resolveInternal($request, $submitter, $workflow, true, false);
    }

    /**
     * Return an explainable resolution without failing on an unresolved optional
     * step. Required unresolved steps are reported for the handoff gate.
     *
     * @return array{reference: string, version: int, workflow_version_id: int|null, context: array<string, mixed>, steps: list<array<string, mixed>>, missing_approvers: list<array<string, mixed>>}
     */
    public function preview(PurchaseRequest $request, User $submitter, ?Workflow $workflow = null): array
    {
        return $this->resolveInternal($request, $submitter, $workflow, false, true);
    }

    /**
     * @return array{reference: string, version: int, workflow_version_id: int|null, context: array<string, mixed>, steps: list<array<string, mixed>>, missing_approvers?: list<array<string, mixed>>}
     */
    private function resolveInternal(
        PurchaseRequest $request,
        User $submitter,
        ?Workflow $workflow,
        bool $failOnMissing,
        bool $includeSkipped,
    ): array {
        $request->loadMissing(['category', 'requester', 'costCenter']);
        $configuredWorkflow = $workflow?->is_active
            ? $workflow
            : (is_string($request->category?->workflow_reference) && $request->category->workflow_reference !== ''
                ? Workflow::query()->where('code', $request->category->workflow_reference)->where('is_active', true)->first()
                : null);
        $reference = $configuredWorkflow?->code ?? $request->category?->workflow_reference;
        $activeVersion = $configuredWorkflow?->activeVersion();

        if ($activeVersion !== null) {
            $reference = $configuredWorkflow->code;
        }

        if (! is_string($reference) || $reference === '') {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow is configured for this procurement category.',
            ]);
        }

        $context = $this->requestContext($request);
        $steps = $activeVersion?->steps()->with('conditions')->get() ?? collect();
        if ($steps->isEmpty()) {
            $steps = collect([new WorkflowStep([
                'sequence' => 1,
                'name' => 'Procurement Review',
                'resolver_type' => 'role_in_request_office',
                'settings' => [],
            ])]);
        }

        $resolvedSteps = [];
        $missingApprovers = [];
        foreach ($steps as $step) {
            if (! $step instanceof WorkflowStep) {
                continue;
            }

            if (! $this->conditionsMatch($step, $request)) {
                if ($includeSkipped) {
                    $resolvedSteps[] = $this->skippedStep($step);
                }

                continue;
            }

            try {
                $resolvedSteps[] = $this->resolveStep($step, $request, $submitter, $context);
            } catch (ValidationException $exception) {
                $explanation = $this->unresolvedStep($step, $exception);
                $missingApprovers[] = $explanation;
                if ($failOnMissing) {
                    throw $exception;
                }
                if ($includeSkipped || $step->is_required) {
                    $resolvedSteps[] = $explanation;
                }
            }
        }

        if ($resolvedSteps === [] && $failOnMissing) {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow step applies to this purchase request.',
            ]);
        }

        return [
            'reference' => $reference,
            'version' => $activeVersion?->version_number ?? 1,
            'workflow_version_id' => $activeVersion?->getKey(),
            'context' => $context,
            'steps' => $resolvedSteps,
            'missing_approvers' => $missingApprovers,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function resolveStep(WorkflowStep $step, PurchaseRequest $request, User $submitter, array $context): array
    {
        $settings = is_array($step->settings) ? $step->settings : [];
        $resolverType = (string) ($step->resolver_type ?? 'role_in_request_office');
        $date = $this->resolutionDate($settings);
        $mappings = $this->mappingsFor($step, $resolverType, $request, $date);
        $allowSelfApproval = (bool) ($settings['allow_self_approval'] ?? false);
        $fallbackResult = 'none';
        $selected = null;

        foreach ($mappings as $mapping) {
            $allowSelfApproval = $allowSelfApproval || $mapping->allow_self_approval;
            $selected = $this->selectAssignment(
                $this->candidateAssignments($mapping, $resolverType, $request, $settings, $date),
                $mapping,
                $request,
                $submitter,
                $allowSelfApproval,
                $date,
            );
            if ($selected !== null) {
                break;
            }

            if ($selected === null && $mapping->user_id !== null) {
                $unavailableAssignment = $this->unavailableMappedAssignment($mapping, $request);
                if ($unavailableAssignment instanceof UserAssignment) {
                    $selected = $this->selectAssignment(
                        [$unavailableAssignment],
                        $mapping,
                        $request,
                        $submitter,
                        $allowSelfApproval,
                        $date,
                    );
                    if ($selected !== null) {
                        break;
                    }
                }
            }
            $fallback = $this->fallbackCandidates($mapping, $request, $settings, $date);
            if ($fallback !== []) {
                $fallbackResult = $mapping->fallback_type;
                $selected = $this->selectAssignment($fallback, $mapping, $request, $submitter, $allowSelfApproval, $date);
                if ($selected !== null) {
                    break;
                }
            }
        }

        if ($selected === null && $mappings === []) {
            $selected = $this->selectAssignment(
                $this->candidateAssignments(null, $resolverType, $request, $settings, $date),
                null,
                $request,
                $submitter,
                $allowSelfApproval,
                $date,
            );
            if ($selected === null) {
                $fallback = $this->configuredFallbackCandidates($request, $settings, $date);
                if ($fallback !== []) {
                    $fallbackResult = 'configured';
                    $selected = $this->selectAssignment($fallback, null, $request, $submitter, $allowSelfApproval, $date);
                }
            }
        }

        if ($selected === null) {
            throw ValidationException::withMessages([
                'workflow' => 'No eligible approver is configured for this purchase request scope. Contact procurement administration.',
            ]);
        }

        /** @var UserAssignment $assignment */
        $assignment = $selected['assignment'];
        /** @var User $user */
        $user = $selected['user'];
        $scopeSource = $selected['scope_source'];
        $stepKey = (string) ($settings['step_key'] ?? Str::snake($step->name));

        $conditions = $this->conditionSnapshot($step);

        return [
            'step_order' => (int) $step->sequence,
            'step_key' => $stepKey,
            'label' => $step->name,
            'step_type' => $step->step_type?->value ?? (string) $step->step_type,
            'approval_mode' => $step->approval_mode?->value ?? (string) $step->approval_mode,
            'resolver_type' => $resolverType,
            'is_required' => (bool) $step->is_required,
            'sla_minutes' => $step->sla_minutes,
            'escalation_type' => $step->escalation_type,
            'settings' => $settings,
            'applicable' => true,
            'conditions' => $conditions,
            'approver_id' => $user->getKey(),
            'original_approver_id' => $selected['delegated_from_user_id'] ?? $user->getKey(),
            'user_id' => $user->getKey(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
            ],
            'approver_name' => $user->name,
            'approver_role' => $assignment->assignedRole?->name ?? $assignment->role,
            'role' => [
                'id' => $assignment->role_id,
                'name' => $assignment->assignedRole?->name ?? $assignment->role,
            ],
            'office_id' => $assignment->office_id,
            'branch_id' => $assignment->branch_id,
            'department_id' => $assignment->department_id,
            'context' => [
                'assignment_id' => $assignment->getKey(),
                'mapping_id' => $selected['mapping_id'],
                'role_id' => $assignment->role_id,
                'office_id' => $assignment->office_id,
                'branch_id' => $assignment->branch_id,
                'department_id' => $assignment->department_id,
                'scope_source' => $scopeSource,
                'fallback_result' => $fallbackResult,
                'delegation_id' => $selected['delegation_id'],
                'delegated_from_user_id' => $selected['delegated_from_user_id'],
                'allow_self_approval' => $allowSelfApproval,
                'conditions' => $conditions,
            ],
            'scope_source' => $scopeSource,
            'fallback_result' => $fallbackResult,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedStep(WorkflowStep $step): array
    {
        $settings = is_array($step->settings) ? $step->settings : [];
        $conditions = $this->conditionSnapshot($step);
        $reason = $conditions === []
            ? 'Step condition did not match the purchase request.'
            : 'Step skipped because configured conditions did not match the purchase request.';

        return [
            'step_order' => (int) $step->sequence,
            'step_key' => (string) (($settings['step_key'] ?? null) ?: Str::snake($step->name)),
            'label' => $step->name,
            'step_type' => $step->step_type?->value ?? (string) $step->step_type,
            'approval_mode' => $step->approval_mode?->value ?? (string) $step->approval_mode,
            'resolver_type' => (string) ($step->resolver_type ?? 'none'),
            'is_required' => (bool) $step->is_required,
            'applicable' => false,
            'conditions' => $conditions,
            'status' => 'skipped',
            'skip_reason' => $reason,
            'approver_id' => null,
            'approver_name' => null,
            'approver_role' => null,
            'scope_source' => null,
            'context' => [
                'conditions' => $conditions,
                'skip_reason' => $reason,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unresolvedStep(WorkflowStep $step, ValidationException $exception): array
    {
        return [
            ...$this->skippedStep($step),
            'applicable' => true,
            'status' => 'unresolved',
            'error' => $exception->errors()['workflow'][0] ?? 'No eligible approver is configured.',
        ];
    }

    /** @return list<array{field_key: string, operator: string, value: mixed}> */
    private function conditionSnapshot(WorkflowStep $step): array
    {
        return $step->conditions
            ->map(fn ($condition): array => [
                'field_key' => $condition->field_key,
                'operator' => $condition->operator instanceof WorkflowConditionOperator
                    ? $condition->operator->value
                    : (string) $condition->operator,
                'value' => $condition->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(PurchaseRequest $request): array
    {
        return [
            'requester_id' => $request->requester_id,
            'office_id' => $request->office_id,
            'branch_id' => $request->branch_id,
            'department_id' => $request->department_id,
            'cost_center_id' => $request->cost_center_id,
            'category_id' => $request->category_id,
            'total_amount' => $request->total_amount,
            'required_date' => $request->required_date?->toDateString(),
            'budget_owner_office_id' => $this->budgetOwnerOfficeId($request),
        ];
    }

    /** @return list<ApproverMapping> */
    private function mappingsFor(WorkflowStep $step, string $resolverType, PurchaseRequest $request, Carbon $date): array
    {
        return ApproverMapping::query()
            ->activeAt($date)
            ->where('resolver_type', $resolverType)
            ->where(function (Builder $query) use ($step): void {
                $query->whereNull('workflow_step_id')->orWhere('workflow_step_id', $step->getKey());
            })
            ->get()
            ->filter(fn (ApproverMapping $mapping): bool => $this->mappingMatches($mapping, $resolverType, $request))
            ->sortByDesc(fn (ApproverMapping $mapping): int => $mapping->priority * 100 + $this->mappingSpecificity($mapping))
            ->values()
            ->all();
    }

    private function mappingMatches(ApproverMapping $mapping, string $resolverType, PurchaseRequest $request): bool
    {
        $officeId = ($mapping->scope_source === 'budget_owner_office' || $resolverType === 'role_in_budget_owner_office')
            ? ($this->budgetOwnerOfficeId($request) ?? $request->office_id)
            : $request->office_id;

        return ($mapping->office_id === null || (int) $mapping->office_id === (int) $officeId)
            && ($mapping->branch_id === null || (int) $mapping->branch_id === (int) $request->branch_id)
            && ($mapping->department_id === null || (int) $mapping->department_id === (int) $request->department_id)
            && ($mapping->cost_center_id === null || (int) $mapping->cost_center_id === (int) $request->cost_center_id);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<UserAssignment>
     */
    private function candidateAssignments(?ApproverMapping $mapping, string $resolverType, PurchaseRequest $request, array $settings, Carbon $date): array
    {
        $officeId = ($mapping?->scope_source === 'budget_owner_office' || $resolverType === 'role_in_budget_owner_office')
            ? ($this->budgetOwnerOfficeId($request) ?? $request->office_id)
            : $request->office_id;
        $roleId = array_key_exists('role_id', $settings)
            ? $this->configuredRoleId($settings)
            : ($mapping?->role_id ?? $this->configuredRoleId($settings));
        $userId = array_key_exists('user_id', $settings)
            ? $this->configuredUserId($settings, $resolverType)
            : ($mapping?->user_id ?? $this->configuredUserId($settings, $resolverType));

        $query = UserAssignment::query()
            ->currentlyActive($date)
            ->withPermission(ProcurementPermissions::APPROVE)
            ->where('office_id', $officeId)
            ->with(['user', 'assignedRole', 'scopes'])
            ->when($roleId !== null, fn (Builder $query): Builder => $query->where('role_id', $roleId))
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $request->branch_id))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('department_id')
                ->orWhere('department_id', $request->department_id))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('cost_center_id')
                ->orWhere('cost_center_id', $request->cost_center_id))
            ->where(function (Builder $query) use ($request): void {
                $query->whereDoesntHave('scopes', fn (Builder $scope): Builder => $scope->where('scope_type', 'category'))
                    ->orWhereHas('scopes', fn (Builder $scope): Builder => $scope
                        ->where('scope_type', 'category')
                        ->where('scope_id', $request->category_id));
            });

        if ($mapping?->department_id !== null) {
            $query->where('department_id', $mapping->department_id);
        }
        if ($mapping?->branch_id !== null) {
            $query->where('branch_id', $mapping->branch_id);
        }
        if ($mapping?->cost_center_id !== null) {
            $query->where('cost_center_id', $mapping->cost_center_id);
        }

        return $query->get()
            ->sortByDesc(fn (UserAssignment $assignment): int => ($assignment->branch_id !== null ? 4 : 0)
                + ($assignment->department_id !== null ? 2 : 0)
                + ($assignment->cost_center_id !== null ? 1 : 0))
            ->values()
            ->all();
    }

    /** @param list<UserAssignment> $assignments @return array<string, mixed>|null */
    private function selectAssignment(array $assignments, ?ApproverMapping $mapping, PurchaseRequest $request, User $submitter, bool $allowSelfApproval, Carbon $date): ?array
    {
        foreach ($assignments as $assignment) {
            $user = $assignment->user;
            if (! $user instanceof User) {
                continue;
            }

            if (! $user->is_active) {
                $delegation = $this->activeDelegation($user, $date);
                if ($delegation !== null) {
                    $delegateAssignment = $this->candidateAssignmentsForUser($delegation->delegate_id, $assignment, $request, $date)->first();
                    if ($delegateAssignment instanceof UserAssignment && $delegateAssignment->user instanceof User
                        && $delegateAssignment->user->is_active
                        && ((int) $delegateAssignment->user->getKey() !== (int) $request->requester_id || $allowSelfApproval)) {
                        $this->auditDelegation($delegation, $assignment, $delegateAssignment, $request, $submitter);

                        return [
                            'assignment' => $delegateAssignment,
                            'user' => $delegateAssignment->user,
                            'mapping_id' => $mapping?->getKey(),
                            'scope_source' => $mapping?->scope_source ?? 'request_office',
                            'delegation_id' => $delegation->getKey(),
                            'delegated_from_user_id' => $user->getKey(),
                        ];
                    }
                }

                continue;
            }

            if ((int) $user->getKey() === (int) $request->requester_id && ! $allowSelfApproval) {
                continue;
            }

            return [
                'assignment' => $assignment,
                'user' => $user,
                'mapping_id' => $mapping?->getKey(),
                'scope_source' => $mapping?->scope_source ?? 'request_office',
                'delegation_id' => null,
                'delegated_from_user_id' => null,
            ];
        }

        return null;
    }

    private function candidateAssignmentsForUser(int|string $userId, UserAssignment $original, PurchaseRequest $request, Carbon $date): Collection
    {
        return UserAssignment::query()
            ->currentlyActive($date)
            ->withPermission(ProcurementPermissions::APPROVE)
            ->where('user_id', $userId)
            ->where('office_id', $original->office_id)
            ->where(fn (Builder $query): Builder => $query->whereNull('branch_id')->orWhere('branch_id', $request->branch_id))
            ->where(fn (Builder $query): Builder => $query->whereNull('department_id')->orWhere('department_id', $request->department_id))
            ->where(fn (Builder $query): Builder => $query->whereNull('cost_center_id')->orWhere('cost_center_id', $request->cost_center_id))
            ->where(function (Builder $query) use ($request): void {
                $query->whereDoesntHave('scopes', fn (Builder $scope): Builder => $scope->where('scope_type', 'category'))
                    ->orWhereHas('scopes', fn (Builder $scope): Builder => $scope
                        ->where('scope_type', 'category')
                        ->where('scope_id', $request->category_id));
            })
            ->with(['user', 'assignedRole'])
            ->orderByDesc('branch_id')
            ->orderByDesc('department_id')
            ->orderBy('id')
            ->get();
    }

    private function unavailableMappedAssignment(ApproverMapping $mapping, PurchaseRequest $request): ?UserAssignment
    {
        return UserAssignment::query()
            ->where('user_id', $mapping->user_id)
            ->where('office_id', $mapping->office_id ?? $request->office_id)
            ->when($mapping->role_id !== null, fn (Builder $query): Builder => $query->where('role_id', $mapping->role_id))
            ->when($mapping->branch_id !== null, fn (Builder $query): Builder => $query->where('branch_id', $mapping->branch_id))
            ->when($mapping->department_id !== null, fn (Builder $query): Builder => $query->where('department_id', $mapping->department_id))
            ->when($mapping->cost_center_id !== null, fn (Builder $query): Builder => $query->where('cost_center_id', $mapping->cost_center_id))
            ->with(['user', 'assignedRole'])
            ->orderByDesc('is_active')
            ->orderByDesc('valid_from')
            ->orderBy('id')
            ->first();
    }

    private function activeDelegation(User $delegator, Carbon $date): ?ApproverDelegation
    {
        return ApproverDelegation::query()
            ->activeAt($date)
            ->where('delegator_id', $delegator->getKey())
            ->with('delegate')
            ->orderByDesc('valid_from')
            ->orderBy('id')
            ->first();
    }

    /** @return list<UserAssignment> */
    private function fallbackCandidates(ApproverMapping $mapping, PurchaseRequest $request, array $settings, Carbon $date): array
    {
        if ($mapping->fallback_type === 'block') {
            return [];
        }

        $fallbackSettings = $settings;
        if ($mapping->fallback_type === 'role') {
            $fallbackSettings['role_id'] = $mapping->fallback_role_id;
            $fallbackSettings['user_id'] = null;
        } else {
            $fallbackSettings['user_id'] = $mapping->fallback_user_id;
            $fallbackSettings['role_id'] = null;
        }

        return $this->candidateAssignments($mapping, $mapping->resolver_type, $request, $fallbackSettings, $date);
    }

    /** @return list<UserAssignment> */
    private function configuredFallbackCandidates(PurchaseRequest $request, array $settings, Carbon $date): array
    {
        $fallback = $settings['fallback'] ?? null;
        if (! is_array($fallback)) {
            return [];
        }

        $fallbackSettings = [
            'role_id' => $fallback['role_id'] ?? null,
            'user_id' => $fallback['user_id'] ?? null,
        ];

        return $this->candidateAssignments(null, 'nominal_role', $request, $fallbackSettings, $date);
    }

    /** @param array<string, mixed> $settings */
    private function configuredRoleId(array $settings): ?int
    {
        $roleId = $settings['role_id'] ?? null;
        if (is_numeric($roleId)) {
            return (int) $roleId;
        }

        $roleName = $settings['role'] ?? $settings['role_name'] ?? $settings['nominal_role'] ?? null;
        if (! is_string($roleName) || $roleName === '') {
            return null;
        }

        return Role::query()
            ->where('is_active', true)
            ->where('guard_name', 'web')
            ->where(fn (Builder $query): Builder => $query->where('id', $roleName)->orWhere('name', $roleName)->orWhere('code', $roleName))
            ->value('id');
    }

    /** @param array<string, mixed> $settings */
    private function configuredUserId(array $settings, string $resolverType): ?int
    {
        $userId = $settings['user_id'] ?? $settings['approver_id'] ?? null;
        if ($resolverType !== 'specific_user' && $userId === null) {
            return null;
        }

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function mappingSpecificity(ApproverMapping $mapping): int
    {
        return collect(['office_id', 'branch_id', 'department_id', 'cost_center_id'])
            ->filter(fn (string $field): bool => $mapping->{$field} !== null)
            ->count();
    }

    private function budgetOwnerOfficeId(PurchaseRequest $request): int|string|null
    {
        return $request->getAttribute('budget_owner_office_id') ?? $request->costCenter?->office_id;
    }

    private function resolutionDate(array $settings): Carbon
    {
        $date = $settings['resolution_date'] ?? null;

        return is_string($date) ? Carbon::parse($date) : Carbon::today();
    }

    private function conditionsMatch(WorkflowStep $step, PurchaseRequest $request): bool
    {
        foreach ($step->conditions as $condition) {
            $actual = data_get($request, $condition->field_key);
            $value = $condition->value;
            $operator = $condition->operator instanceof WorkflowConditionOperator ? $condition->operator->value : (string) $condition->operator;

            $matches = match ($operator) {
                'equals' => (string) $actual === (string) ($value[0] ?? $value),
                'not_equals' => (string) $actual !== (string) ($value[0] ?? $value),
                'in' => in_array((string) $actual, array_map('strval', is_array($value) ? $value : [$value]), true),
                'gte' => is_numeric($actual) && (float) $actual >= (float) ($value[0] ?? $value),
                'lte' => is_numeric($actual) && (float) $actual <= (float) ($value[0] ?? $value),
                'between' => is_numeric($actual) && is_array($value) && count($value) === 2 && (float) $actual >= (float) $value[0] && (float) $actual <= (float) $value[1],
                default => false,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    private function auditDelegation(
        ApproverDelegation $delegation,
        UserAssignment $original,
        UserAssignment $delegate,
        PurchaseRequest $request,
        User $actor,
    ): void {
        activity('workflow')
            ->performedOn($delegation)
            ->causedBy($actor)
            ->withProperties([
                'delegation_id' => $delegation->getKey(),
                'delegator_id' => $delegation->delegator_id,
                'delegate_id' => $delegation->delegate_id,
                'original_assignment_id' => $original->getKey(),
                'delegate_assignment_id' => $delegate->getKey(),
                'purchase_request_id' => $request->getKey(),
                'valid_from' => $delegation->valid_from?->toDateString(),
                'valid_until' => $delegation->valid_until?->toDateString(),
            ])
            ->log('Approver delegation applied');
    }
}
