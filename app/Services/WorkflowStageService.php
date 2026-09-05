<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\PurchaseRequest;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class WorkflowStageService
{
    /**
     * Terminal statuses that are not workflow steps.
     */
    public const TERMINAL_STATUSES = [
        'approved',
        'rejected',
        'returned',
        'cancelled',
        'completed',
    ];

    public const INITIAL_STATUSES = [
        'draft',
        'submitted',
    ];

    /**
     * Get workflow for a purchase request (dynamic per category).
     */
    public function workflowFor(PurchaseRequest $request): ?Workflow
    {
        $request->loadMissing(['category']);

        $code = $request->category?->workflow_reference;
        if (is_string($code) && $code !== '') {
            $workflow = Workflow::query()->where('code', $code)->where('is_active', true)->first();
            if ($workflow instanceof Workflow) {
                return $workflow;
            }
        }

        return Workflow::query()->where('code', 'standard-procurement')->where('is_active', true)->first();
    }

    /**
     * Get ordered workflow steps for a request.
     *
     * @return Collection<int, WorkflowStep>
     */
    public function stepsFor(PurchaseRequest $request): Collection
    {
        $workflow = $this->workflowFor($request);
        if (! $workflow instanceof Workflow) {
            return collect();
        }

        $version = $workflow->activeVersion();
        if ($version === null) {
            return collect();
        }

        return $version->steps()->orderBy('sequence')->get();
    }

    /**
     * Get all dynamic stage keys for this request (step_keys).
     *
     * @return list<string>
     */
    public function stageKeysFor(PurchaseRequest $request): array
    {
        return $this->stepsFor($request)
            ->map(fn (WorkflowStep $step): string => $this->stepKey($step))
            ->values()
            ->all();
    }

    /**
     * Get ordered stages for progress stepper: draft, submitted, workflow steps, approved.
     *
     * @return list<string>
     */
    public function orderedStages(PurchaseRequest $request): array
    {
        $stages = ['draft', 'submitted'];
        $stages = [...$stages, ...$this->stageKeysFor($request)];
        $stages[] = 'approved';

        return array_values(array_unique($stages));
    }

    /**
     * Check if a status string is a dynamic workflow stage (step_key) for given PR.
     */
    public function isDynamicStage(string $status, ?PurchaseRequest $request = null): bool
    {
        if (in_array($status, PurchaseRequestStatus::values(), true)) {
            return false;
        }

        if ($request === null) {
            // Without request context, treat any snake_case not in enum as dynamic
            return (bool) preg_match('/^[a-z0-9_]+$/', $status) && strlen($status) <= 100;
        }

        return in_array($status, $this->stageKeysFor($request), true);
    }

    /**
     * Check if status is terminal (approved/rejected/returned/cancelled/completed).
     */
    public function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Check if status is initial (draft/submitted).
     */
    public function isInitial(string $status): bool
    {
        return in_array($status, self::INITIAL_STATUSES, true);
    }

    /**
     * Get label for a status (dynamic or enum) for display.
     */
    public function labelFor(string $status, ?PurchaseRequest $request = null): string
    {
        // Try enum first
        $enum = PurchaseRequestStatus::tryFrom($status);
        if ($enum instanceof PurchaseRequestStatus) {
            return match ($enum) {
                PurchaseRequestStatus::Draft => 'Draft',
                PurchaseRequestStatus::Submitted => 'Diajukan',
                PurchaseRequestStatus::ProcurementReview => 'Review Pengadaan',
                PurchaseRequestStatus::PendingApproval => 'Menunggu Persetujuan',
                PurchaseRequestStatus::Approved => 'Disetujui',
                PurchaseRequestStatus::Rejected => 'Ditolak',
                PurchaseRequestStatus::Returned => 'Perlu Perbaikan',
                PurchaseRequestStatus::Completed => 'Selesai',
                PurchaseRequestStatus::Cancelled => 'Dibatalkan',
            };
        }

        // Dynamic workflow stage
        if ($request instanceof PurchaseRequest) {
            $steps = $this->stepsFor($request);
            foreach ($steps as $step) {
                if ($this->stepKey($step) === $status) {
                    return $step->name;
                }
            }
        }

        // Fallback: humanize step_key
        return Str::headline(str_replace('_', ' ', $status));
    }

    /**
     * Get current dynamic stage for a PR based on pending approval step.
     */
    public function currentStageFor(PurchaseRequest $request): string
    {
        $request->loadMissing(['approvalInstances.steps']);

        $instance = $request->approvalInstances->sortByDesc('created_at')->first()
            ?? $request->approvalInstances()->latest()->first();

        if ($instance instanceof ApprovalInstance) {
            $pending = $instance->steps()->whereIn('status', ['pending', 'in_progress'])->orderBy('step_order')->first();
            if ($pending instanceof ApprovalInstanceStep) {
                return $pending->step_key;
            }

            // If no pending, check last completed
            $last = $instance->steps()->orderByDesc('step_order')->first();
            if ($last instanceof ApprovalInstanceStep && $last->status === 'approved') {
                return PurchaseRequestStatus::Approved->value;
            }
        }

        return $request->status;
    }

    /**
     * Get step_key for a WorkflowStep (handles settings step_key override).
     */
    public function stepKey(WorkflowStep $step): string
    {
        $settings = is_array($step->settings) ? $step->settings : [];
        $key = $settings['step_key'] ?? null;
        if (is_string($key) && $key !== '') {
            return $key;
        }

        return Str::snake($step->name);
    }

    /**
     * Validate if status string is allowed (enum or dynamic step_key).
     */
    public function isValidStatus(string $status, ?PurchaseRequest $request = null): bool
    {
        if (PurchaseRequestStatus::tryFrom($status) !== null) {
            return true;
        }

        return $this->isDynamicStage($status, $request);
    }
}
