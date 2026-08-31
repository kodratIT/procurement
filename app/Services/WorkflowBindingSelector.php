<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowBinding;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WorkflowBindingSelector
{
    /** @param array<string, mixed> $context */
    public function select(array $context): WorkflowBinding
    {
        $workflowId = $context['workflow_id'] ?? null;
        $bindings = WorkflowBinding::query()->where('is_active', true)
            ->when($workflowId !== null, fn ($query) => $query->where('workflow_id', $workflowId))
            ->get();
        $matches = $this->matching($bindings, $context);

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages(['workflow' => 'No active workflow binding matches this input.']);
        }

        $ranked = $matches->sortByDesc(fn (WorkflowBinding $binding): array => [$this->specificity($binding), (int) $binding->priority, -$binding->getKey()]);
        $winner = $ranked->first();
        $top = $ranked->filter(fn (WorkflowBinding $binding): bool => $this->specificity($binding) === $this->specificity($winner) && (int) $binding->priority === (int) $winner->priority);

        if ($top->count() > 1) {
            throw ValidationException::withMessages(['workflow' => 'Workflow binding is ambiguous: matching rules have equal specificity and priority.']);
        }

        return $winner;
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function simulate(array $context): array
    {
        $binding = $this->select($context);

        return [
            'binding_id' => $binding->getKey(),
            'workflow_id' => $binding->workflow_id,
            'priority' => $binding->priority,
            'specificity' => $this->specificity($binding),
            'context' => $context,
        ];
    }

    public function validate(?Workflow $workflow = null): void
    {
        $bindings = $workflow?->bindings()->where('is_active', true)->get()
            ?? WorkflowBinding::query()->where('is_active', true)->get();

        foreach ($bindings as $binding) {
            if ($binding->minimum_amount !== null && $binding->maximum_amount !== null && (float) $binding->minimum_amount > (float) $binding->maximum_amount) {
                throw ValidationException::withMessages(['bindings' => 'A workflow binding minimum amount cannot exceed its maximum amount.']);
            }
            $this->validateConditions($binding->conditions ?? []);
        }

        for ($i = 0; $i < $bindings->count(); $i++) {
            for ($j = $i + 1; $j < $bindings->count(); $j++) {
                $left = $bindings[$i];
                $right = $bindings[$j];
                if ($this->specificity($left) === $this->specificity($right)
                    && (int) $left->priority === (int) $right->priority
                    && $this->overlaps($left, $right)) {
                    throw ValidationException::withMessages(['bindings' => 'Workflow bindings are ambiguous: equal-priority rules overlap.']);
                }
            }
        }
    }

    private function specificity(WorkflowBinding $binding): int
    {
        return collect(['transaction_type', 'category_id', 'office_id', 'branch_id', 'department_id', 'cost_center_id'])
            ->filter(fn (string $field): bool => $binding->{$field} !== null)
            ->count() + (($binding->minimum_amount !== null || $binding->maximum_amount !== null) ? 1 : 0)
            + count($binding->conditions ?? []);
    }

    /** @param Collection<int, WorkflowBinding> $bindings @param array<string, mixed> $context */
    private function matching(Collection $bindings, array $context): Collection
    {
        return $bindings->filter(function (WorkflowBinding $binding) use ($context): bool {
            foreach (['transaction_type', 'category_id', 'office_id', 'branch_id', 'department_id', 'cost_center_id'] as $field) {
                if ($binding->{$field} !== null && (string) $binding->{$field} !== (string) ($context[$field] ?? '')) {
                    return false;
                }
            }
            $amount = isset($context['amount']) ? (float) $context['amount'] : null;
            if ($amount !== null && (($binding->minimum_amount !== null && $amount < (float) $binding->minimum_amount) || ($binding->maximum_amount !== null && $amount > (float) $binding->maximum_amount))) {
                return false;
            }
            if ($amount === null && ($binding->minimum_amount !== null || $binding->maximum_amount !== null)) {
                return false;
            }
            foreach ($binding->conditions ?? [] as $field => $expected) {
                $actual = $context[$field] ?? null;
                if (is_array($expected)) {
                    if (count($expected) === 2 && is_numeric($expected[0]) && is_numeric($expected[1])) {
                        if (! is_numeric($actual) || (float) $actual < (float) $expected[0] || (float) $actual > (float) $expected[1]) {
                            return false;
                        }
                    } elseif (! in_array($actual, $expected, true)) {
                        return false;
                    }
                } elseif ($actual != $expected) {
                    return false;
                }
            }

            return true;
        });
    }

    private function overlaps(WorkflowBinding $left, WorkflowBinding $right): bool
    {
        foreach (['transaction_type', 'category_id', 'office_id', 'branch_id', 'department_id', 'cost_center_id'] as $field) {
            if ($left->{$field} !== null && $right->{$field} !== null && (string) $left->{$field} !== (string) $right->{$field}) {
                return false;
            }
        }
        $leftMin = $left->minimum_amount === null ? -INF : (float) $left->minimum_amount;
        $leftMax = $left->maximum_amount === null ? INF : (float) $left->maximum_amount;
        $rightMin = $right->minimum_amount === null ? -INF : (float) $right->minimum_amount;
        $rightMax = $right->maximum_amount === null ? INF : (float) $right->maximum_amount;

        return max($leftMin, $rightMin) <= min($leftMax, $rightMax) && $this->conditionsOverlap($left->conditions ?? [], $right->conditions ?? []);
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function conditionsOverlap(array $left, array $right): bool
    {
        foreach ($left as $field => $value) {
            if (! array_key_exists($field, $right)) {
                continue;
            }
            $other = $right[$field];
            if (is_array($value) && is_array($other)) {
                if (count($value) === 2 && count($other) === 2 && is_numeric($value[0]) && is_numeric($value[1]) && is_numeric($other[0]) && is_numeric($other[1])) {
                    if (max((float) $value[0], (float) $other[0]) > min((float) $value[1], (float) $other[1])) {
                        return false;
                    }
                } elseif (array_intersect($value, $other) === []) {
                    return false;
                }
            } elseif (
                is_array($value)
                    ? ! in_array($other, $value, true)
                    : (is_array($other) ? ! in_array($value, $other, true) : $value != $other)
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $conditions */
    private function validateConditions(array $conditions): void
    {
        foreach ($conditions as $field => $value) {
            if (! is_string($field) || $field === '') {
                throw ValidationException::withMessages(['conditions' => 'Workflow condition fields must be non-empty strings.']);
            }
            if (is_array($value) && ($value === [] || (count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1]) && (float) $value[0] > (float) $value[1]))) {
                throw ValidationException::withMessages(['conditions' => 'Workflow condition ranges are invalid.']);
            }
        }
    }
}
