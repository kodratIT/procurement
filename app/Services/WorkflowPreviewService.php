<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowBinding;
use Illuminate\Validation\ValidationException;

final class WorkflowPreviewService
{
    public function __construct(
        private readonly WorkflowBindingSelector $bindings,
        private readonly WorkflowResolver $resolver,
    ) {}

    /**
     * Build the explainable workflow and approver resolution for a request.
     *
     * @return array<string, mixed>
     */
    public function preview(PurchaseRequest $request, ?User $actor = null): array
    {
        $request->loadMissing([
            'category',
            'requester',
            'office',
            'branch',
            'department',
            'costCenter.office',
        ]);
        $actor ??= $request->requester;

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'workflow' => 'An active user is required to preview the approval workflow.',
            ]);
        }

        $context = $this->bindingContext($request);
        $workflow = $this->workflowFor($request, $context);
        $resolution = $this->resolver->preview($request, $actor, $workflow);
        $missing = collect($resolution['missing_approvers'] ?? [])
            ->filter(fn (mixed $step): bool => is_array($step) && (bool) ($step['is_required'] ?? true))
            ->values()
            ->all();

        return [
            'can_handoff' => $missing === [],
            'errors' => array_values(array_map(
                static fn (array $step): string => sprintf(
                    'Step %s (%s): %s',
                    $step['step_order'] ?? '?',
                    $step['label'] ?? 'Approval',
                    $step['error'] ?? 'Required approver is not configured.',
                ),
                $missing,
            )),
            'workflow' => [
                'id' => $workflow->getKey(),
                'code' => $workflow->code,
                'name' => $workflow->name,
                'version' => $resolution['version'],
                'version_id' => $resolution['workflow_version_id'],
                'binding_id' => $context['binding_id'] ?? null,
            ],
            'source' => [
                'office_id' => $request->office_id,
                'office_name' => $request->office?->name,
                'branch_id' => $request->branch_id,
                'branch_name' => $request->branch?->name,
                'department_id' => $request->department_id,
                'department_name' => $request->department?->name,
                'cost_center_id' => $request->cost_center_id,
            ],
            'budget_owner' => $this->budgetOwner($request),
            'steps' => array_values($resolution['steps']),
            'resolution_context' => $resolution['context'],
            'resolution' => $resolution,
        ];
    }

    /** @return array<string, mixed> */
    private function bindingContext(PurchaseRequest $request): array
    {
        $context = [
            'transaction_type' => 'purchase_request',
            'category_id' => $request->category_id,
            'office_id' => $request->office_id,
            'branch_id' => $request->branch_id,
            'department_id' => $request->department_id,
            'cost_center_id' => $request->cost_center_id,
            'amount' => $request->total_amount,
            'priority' => $request->priority,
        ];
        $binding = null;

        if (WorkflowBinding::query()->where('is_active', true)->exists()) {
            $binding = $this->bindings->select($context);
            $context['binding_id'] = $binding->getKey();
        }

        return $context;
    }

    /** @param array<string, mixed> $context */
    private function workflowFor(PurchaseRequest $request, array $context): Workflow
    {
        $workflow = isset($context['binding_id'])
            ? WorkflowBinding::query()->with('workflow')->findOrFail($context['binding_id'])->workflow
            : Workflow::query()
                ->where('code', $request->category?->workflow_reference)
                ->where('is_active', true)
                ->first();

        // ponytail: JAMAAH/HOTEL/TRANSPORT reference workflows that were never seeded — fallback to standard-procurement instead of throwing
        if (! $workflow instanceof Workflow || ! $workflow->is_active) {
            $workflow = Workflow::query()->where('code', 'standard-procurement')->where('is_active', true)->first();
        }

        if (! $workflow instanceof Workflow || ! $workflow->is_active) {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow is configured for this procurement category.',
            ]);
        }

        return $workflow;
    }

    /** @return array<string, mixed> */
    private function budgetOwner(PurchaseRequest $request): array
    {
        $office = $request->costCenter?->office ?? $request->office;

        return [
            'office_id' => $office?->getKey(),
            'office_name' => $office?->name,
            'source' => $request->costCenter?->office instanceof Office ? 'cost_center' : 'request_office',
        ];
    }
}
