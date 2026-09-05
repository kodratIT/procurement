<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProcurementFieldType;
use App\Enums\PurchaseRequestStatus;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\DomainTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ProcurementRequestSubmitter
{
    public function __construct(
        private readonly AccessContextService $context,
        private readonly DynamicFieldValidator $dynamicFields,
        private readonly PurchaseRequestNumberService $numbers,
        private readonly PurchaseRequestTimeline $timeline,
        private readonly DomainTransaction $transaction,
        private readonly FeatureModuleService $featureModules,
        private readonly WorkflowPreviewService $workflowPreview,
        private readonly ApprovalInstanceCreator $approvalInstances,
        private readonly WorkflowStageService $workflowStages,
    ) {}

    public function submit(PurchaseRequest $request, ?User $submitter = null): PurchaseRequest
    {
        $submitter ??= auth()->user();

        if (! $submitter instanceof User || ! $submitter->is_active) {
            throw new AuthorizationException('An active authenticated submitter is required.');
        }

        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_REQUESTS, $submitter);

        Gate::forUser($submitter)->authorize('submit', $request);

        if ($this->context->assignment() === null) {
            throw new AuthorizationException('An active office context is required.');
        }
        $request->loadMissing(['category', 'items', 'fieldValues']);
        $this->validateFinalSubmission($request);

        return $this->transaction->run(
            'submit purchase request',
            function () use ($request, $submitter): PurchaseRequest {
                $locked = PurchaseRequest::query()
                    ->lockForUpdate()
                    ->with(['category', 'items', 'fieldValues'])
                    ->findOrFail($request->getKey());

                $this->validateFinalSubmission($locked);

                $locked->loadMissing(['category']);
                $workflowCode = $locked->category?->workflow_reference;
                $shouldBypassProcurement = is_string($workflowCode) && $workflowCode !== '' && $workflowCode !== 'standard-procurement';
                $preview = null;

                if ($shouldBypassProcurement) {
                    $preview = $this->workflowPreview->preview($locked, $submitter);
                    $errors = array_values(array_filter($preview['errors'] ?? []));

                    if (($preview['can_handoff'] ?? false) !== true || empty($preview['resolution']['steps'])) {
                        throw ValidationException::withMessages([
                            'workflow' => $errors !== []
                                ? $errors
                                : ['Workflow belum dapat dijalankan karena approver belum lengkap.'],
                        ]);
                    }
                }

                $fromStatus = $locked->status;
                $number = $fromStatus === PurchaseRequestStatus::Returned->value
                    ? $locked->pr_number
                    : $this->numbers->next();
                $updates = [
                    'status' => PurchaseRequestStatus::Submitted->value,
                    'updated_at' => now(),
                ];
                if ($fromStatus !== PurchaseRequestStatus::Returned->value) {
                    $updates['pr_number'] = $number;
                }

                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update($updates);
                $locked->refresh();
                $this->timeline->record(
                    $locked,
                    $submitter,
                    $fromStatus,
                    PurchaseRequestStatus::Submitted->value,
                    $fromStatus === PurchaseRequestStatus::Returned->value ? 'resubmitted' : 'submitted',
                    'submit',
                    $fromStatus === PurchaseRequestStatus::Returned->value
                        ? "PR {$locked->pr_number} telah diperbaiki dan diajukan ulang oleh {$submitter->name}."
                        : "PR {$locked->pr_number} diajukan oleh {$submitter->name} dan menunggu proses persetujuan.",
                );
                $this->audit($locked, $submitter, $fromStatus);

                // Dynamic workflow is handed off directly to its first approver.
                if ($shouldBypassProcurement) {
                    $firstStep = collect($preview['resolution']['steps'])->firstWhere(fn ($s) => ($s['applicable'] ?? true) && ($s['status'] ?? null) !== 'skipped' && ($s['status'] ?? null) !== 'unresolved');
                    $nextStatus = $firstStep['step_key'] ?? null;

                    if (! is_array($firstStep) || ! is_string($nextStatus) || $nextStatus === '') {
                        throw ValidationException::withMessages([
                            'workflow' => ['Workflow belum memiliki tahap approval yang dapat dijalankan.'],
                        ]);
                    }

                    $instance = $this->approvalInstances->create($locked, $submitter, $preview['resolution']);
                    $fromSubmitted = $locked->status;
                    PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                        'status' => $nextStatus,
                        'updated_at' => now(),
                    ]);
                    $locked->refresh();
                    $firstApprover = $firstStep['approver_name'] ?? 'approver';
                    $this->timeline->record(
                        $locked,
                        $submitter,
                        $fromSubmitted,
                        $nextStatus,
                        'approval_handoff',
                        'handoff',
                        "PR masuk ke tahap {$firstStep['label']} dan menunggu persetujuan {$firstApprover}.",
                        ['approval_instance_id' => $instance->getKey(), 'workflow' => $preview['workflow'], 'auto' => true],
                    );
                }

                return $locked->refresh();
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $submitter->getKey(),
            ],
        );
    }

    private function validateFinalSubmission(PurchaseRequest $request): void
    {
        $errors = [];

        if (! in_array($request->status, [PurchaseRequestStatus::Draft->value, PurchaseRequestStatus::Returned->value], true)) {
            $errors['status'] = 'Only a draft or returned purchase request can be submitted.';
        }

        if ($request->category === null || ! $request->category->is_active) {
            $errors['category_id'] = 'An active procurement category is required before submission.';
        } elseif ($request->category->configuration()->requiresBatch() && $request->umrah_batch_id === null) {
            $errors['umrah_batch_id'] = 'A umrah batch is required for this procurement category.';
        }

        if (blank($request->reason)) {
            $errors['reason'] = 'A reason is required before submission.';
        }

        if ($request->items->isEmpty()) {
            $errors['items'] = 'At least one purchase request item is required before submission.';
        }

        foreach ($request->items as $index => $item) {
            if (! is_numeric($item->quantity) || (float) $item->quantity <= 0) {
                $errors["items.{$index}.quantity"] = 'Each item quantity must be greater than zero.';
            }

            if (! is_numeric($item->unit_price) || (float) $item->unit_price < 0) {
                $errors["items.{$index}.unit_price"] = 'Each item unit price must be zero or greater.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->validateDynamicFields($request);
    }

    private function validateDynamicFields(PurchaseRequest $request): void
    {
        $fields = ProcurementField::query()
            ->where('category_id', $request->category_id)
            ->where('is_active', true)
            ->ordered()
            ->get();
        $values = $request->fieldValues
            ->mapWithKeys(fn ($value): array => [$value->field_key => $value->value])
            ->all();
        $visibleFields = $this->dynamicFields->visibleFields($fields, $values);
        $errors = [];

        foreach ($visibleFields as $field) {
            if ($field->is_required && blank($values[$field->key] ?? null)) {
                $errors['fields.'.$field->key] = $field->label.' is required before submission.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $validationFields = array_filter(
            $visibleFields,
            fn (ProcurementField $field): bool => $field->field_type !== ProcurementFieldType::Upload,
        );

        if ($validationFields !== []) {
            $this->dynamicFields->validate($validationFields, ['fields' => $values]);
        }
    }

    private function audit(PurchaseRequest $request, User $actor, string $fromStatus): void
    {
        activity('procurement')
            ->performedOn($request)
            ->causedBy($actor)
            ->event('submitted')
            ->withProperties([
                'before' => ['status' => $fromStatus],
                'after' => ['status' => PurchaseRequestStatus::Submitted->value, 'pr_number' => $request->pr_number],
                'requester_id' => $request->requester_id,
                'office_id' => $request->office_id,
                'branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
            ])
            ->log($fromStatus === PurchaseRequestStatus::Returned->value
                ? 'Purchase request corrected and resubmitted'
                : 'Purchase request submitted');
    }
}
