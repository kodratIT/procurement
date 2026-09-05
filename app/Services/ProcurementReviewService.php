<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFieldValue;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ProcurementReviewService
{
    /** @var list<string> */
    private const REVIEW_STATUSES = [
        PurchaseRequestStatus::Submitted->value,
        PurchaseRequestStatus::ProcurementReview->value,
    ];

    /** @var list<string> */
    private const EDITABLE_ITEM_FIELDS = [
        'quantity',
        'unit_price',
        'description',
        'specifications',
        'notes',
    ];

    public function __construct(
        private readonly AccessContextService $context,
        private readonly DynamicFieldValidator $dynamicFields,
        private readonly PurchaseRequestTotalCalculator $totals,
        private readonly DomainTransaction $transaction,
        private readonly PurchaseRequestTimeline $timeline,
        private readonly WorkflowPreviewService $workflowPreview,
        private readonly ApprovalInstanceCreator $approvalInstances,
        private readonly FeatureModuleService $featureModules,
        private readonly WorkflowStageService $workflowStages,
    ) {}

    /**
     * Return the review queue for every active procurement assignment of the user.
     *
     * @return Builder<PurchaseRequest>
     */
    public function reviewQueue(?User $reviewer = null): Builder
    {
        $reviewer = $this->activeReviewer($reviewer);
        $assignments = $this->reviewAssignments($reviewer);

        $query = PurchaseRequest::query()
            ->withoutGlobalScopes()
            ->with(['requester', 'office', 'branch', 'department', 'category', 'vendor', 'items', 'fieldValues', 'attachments'])
            ->whereIn('status', self::REVIEW_STATUSES);

        if ($assignments->isEmpty()) {
            return $query->whereKey(0);
        }

        return $query->where(function (Builder $query) use ($assignments): void {
            foreach ($assignments as $assignment) {
                $query->orWhere(function (Builder $query) use ($assignment): void {
                    $query->where('office_id', $assignment->office_id);

                    if ($assignment->branch_id !== null) {
                        $query->where('branch_id', $assignment->branch_id);
                    }

                    if ($assignment->department_id !== null) {
                        $query->where('department_id', $assignment->department_id);
                    }

                    $categoryIds = $assignment->scopes
                        ->where('scope_type', 'category')
                        ->pluck('scope_id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->filter()
                        ->values()
                        ->all();

                    if ($categoryIds !== []) {
                        $query->whereIn('category_id', $categoryIds);
                    }
                });
            }
        });
    }

    /**
     * Alias used by queue consumers that treat the review queue as a query.
     *
     * @return Builder<PurchaseRequest>
     */
    public function queue(?User $reviewer = null): Builder
    {
        return $this->reviewQueue($reviewer);
    }

    /**
     * Mark a submitted request as being actively reviewed by procurement.
     */
    public function forward(
        PurchaseRequest $request,
        User|string|null $reviewerOrReason = null,
        ?User $reviewer = null,
    ): PurchaseRequest {
        [$reviewer, $reason] = $this->reviewerAndReason($reviewerOrReason, $reviewer);
        $this->authorizeReview($request, $reviewer);

        if ($request->status !== PurchaseRequestStatus::Submitted->value) {
            throw ValidationException::withMessages([
                'status' => 'Only a submitted purchase request can be forwarded to procurement review.',
            ]);
        }

        return $this->transaction->run(
            'forward purchase request to procurement review',
            function () use ($request, $reviewer, $reason): PurchaseRequest {
                $locked = PurchaseRequest::query()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items', 'fieldValues'])
                    ->findOrFail($request->getKey());
                $fromStatus = $locked->status;

                if ($fromStatus !== PurchaseRequestStatus::Submitted->value) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a submitted purchase request can be forwarded to procurement review.',
                    ]);
                }

                $this->audit(
                    $locked,
                    $reviewer,
                    'forwarded',
                    ['status' => $fromStatus],
                    ['status' => PurchaseRequestStatus::ProcurementReview->value],
                    $reason,
                );
                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                    'status' => PurchaseRequestStatus::ProcurementReview->value,
                    'updated_at' => now(),
                ]);
                $locked->refresh();
                $this->timeline->record(
                    $locked,
                    $reviewer,
                    $fromStatus,
                    PurchaseRequestStatus::ProcurementReview->value,
                    'forwarded',
                    'forward',
                    $reason,
                );

                return $locked;
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $reviewer->getKey(),
            ],
        );
    }

    /**
     * Resolve and hand a reviewed purchase request to its approval workflow.
     * Dynamic: supports any workflow stages, not just hardcode procurement_review -> pending_approval.
     */
    public function handoffToApproval(PurchaseRequest $request, ?User $reviewer = null): PurchaseRequest
    {
        $reviewer = $this->activeReviewer($reviewer);
        Gate::forUser($reviewer)->authorize('handoff', $request);
        $this->assertReviewContext($request, $reviewer);

        // Dynamic: allow handoff from submitted or procurement_review or any workflow stage that is review-like
        $allowedHandoffStatuses = [
            PurchaseRequestStatus::Submitted->value,
            PurchaseRequestStatus::ProcurementReview->value,
            ...$this->workflowStages->stageKeysFor($request),
        ];
        if (! in_array($request->status, $allowedHandoffStatuses, true) && ! $this->workflowStages->isDynamicStage($request->status, $request)) {
            throw ValidationException::withMessages([
                'status' => 'Only a purchase request in procurement review can be handed off to approval.',
            ]);
        }

        $preview = $this->workflowPreview->preview($request, $reviewer);
        $this->assertHandoffPreview($preview);

        return $this->transaction->run(
            'handoff purchase request to approval',
            function () use ($request, $reviewer, $preview): PurchaseRequest {
                $locked = PurchaseRequest::query()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['category', 'requester', 'office', 'branch', 'department', 'costCenter.office', 'items'])
                    ->findOrFail($request->getKey());

                $allowedLockedStatuses = [
                    PurchaseRequestStatus::Submitted->value,
                    PurchaseRequestStatus::ProcurementReview->value,
                    ...$this->workflowStages->stageKeysFor($locked),
                ];
                if (! in_array($locked->status, $allowedLockedStatuses, true) && ! $this->workflowStages->isDynamicStage($locked->status, $locked)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a purchase request in procurement review can be handed off to approval.',
                    ]);
                }

                if ($locked->approvalInstances()->whereIn('status', ['pending', 'in_progress'])->exists()) {
                    throw ValidationException::withMessages([
                        'workflow' => 'This purchase request already has an active approval instance.',
                    ]);
                }

                $instance = $this->approvalInstances->create($locked, $reviewer, $preview['resolution']);
                $fromStatus = $locked->status;
                // Dynamic: set status to first workflow step's step_key if available, fallback to pending_approval
                $firstStep = collect($preview['resolution']['steps'] ?? [])->firstWhere(fn ($s) => ($s['applicable'] ?? true) && ($s['status'] ?? null) !== 'skipped' && ($s['status'] ?? null) !== 'unresolved');
                $nextStatus = $firstStep['step_key'] ?? PurchaseRequestStatus::PendingApproval->value;
                // Validate nextStatus length and fallback
                if (! is_string($nextStatus) || $nextStatus === '') {
                    $nextStatus = PurchaseRequestStatus::PendingApproval->value;
                }
                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                    'status' => $nextStatus,
                    'updated_at' => now(),
                ]);
                $locked->refresh();
                $firstLabel = $firstStep['label'] ?? $nextStatus;
                $firstApprover = $firstStep['approver_name'] ?? 'approver';
                $this->timeline->record(
                    $locked,
                    $reviewer,
                    $fromStatus,
                    $nextStatus,
                    'approval_handoff',
                    'handoff',
                    "PR masuk ke tahap {$firstLabel} dan menunggu persetujuan {$firstApprover}.",
                    [
                        'approval_instance_id' => $instance->getKey(),
                        'workflow' => $preview['workflow'],
                    ],
                );
                activity('procurement')
                    ->performedOn($locked)
                    ->causedBy($reviewer)
                    ->event('approval_handoff')
                    ->withProperties([
                        'before' => ['status' => $fromStatus],
                        'after' => ['status' => $nextStatus],
                        'approval_instance_id' => $instance->getKey(),
                        'workflow' => $preview['workflow'],
                        'steps' => $preview['steps'],
                    ])
                    ->log('Purchase request handed off to approval');

                return $locked->load('approvalInstances.steps');
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $reviewer->getKey(),
            ],
        );
    }

    public function forwardToApproval(PurchaseRequest $request, ?User $reviewer = null): PurchaseRequest
    {
        return $this->handoffToApproval($request, $reviewer);
    }

    /**
     * Return a request to its requester. A reason is always required.
     */
    public function returnToRequester(
        PurchaseRequest $request,
        string $reason,
        ?User $reviewer = null,
    ): PurchaseRequest {
        $reviewer = $this->activeReviewer($reviewer);

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when returning a purchase request.',
            ]);
        }

        Gate::forUser($reviewer)->authorize('return', $request);
        $this->assertReviewContext($request, $reviewer);

        return $this->transaction->run(
            'return purchase request for correction',
            function () use ($request, $reviewer, $reason): PurchaseRequest {
                $locked = PurchaseRequest::query()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($request->getKey());
                $fromStatus = $locked->status;

                if (! in_array($fromStatus, self::REVIEW_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a submitted purchase request can be returned for correction.',
                    ]);
                }

                $this->audit(
                    $locked,
                    $reviewer,
                    'returned',
                    ['status' => $fromStatus],
                    ['status' => PurchaseRequestStatus::Returned->value],
                    $reason,
                );
                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                    'status' => PurchaseRequestStatus::Returned->value,
                    'updated_at' => now(),
                ]);
                $locked->refresh();
                $this->timeline->record(
                    $locked,
                    $reviewer,
                    $fromStatus,
                    PurchaseRequestStatus::Returned->value,
                    'returned',
                    'return',
                    $reason,
                );

                return $locked;
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $reviewer->getKey(),
            ],
        );
    }

    /**
     * Persist only procurement-controlled corrections on a reviewed request.
     *
     * @param  array<string, mixed>  $changes
     */
    public function edit(
        PurchaseRequest $request,
        array $changes,
        string $reason,
        ?User $reviewer = null,
    ): PurchaseRequest {
        $reviewer = $this->activeReviewer($reviewer);

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for procurement corrections.',
            ]);
        }

        Gate::forUser($reviewer)->authorize('review', $request);
        $this->assertReviewContext($request, $reviewer);
        $this->assertAllowedChangeKeys($changes);

        return $this->transaction->run(
            'edit purchase request during procurement review',
            function () use ($request, $changes, $reason, $reviewer): PurchaseRequest {
                $locked = PurchaseRequest::query()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items', 'fieldValues', 'category'])
                    ->findOrFail($request->getKey());

                if (! in_array($locked->status, self::REVIEW_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a submitted purchase request can be edited during review.',
                    ]);
                }

                $itemChanges = $this->prepareItemChanges($locked, $changes['items'] ?? null);
                [$fieldChanges, $fieldValues] = $this->prepareFieldChanges($locked, $changes['fields'] ?? null);
                $vendorChange = $this->prepareVendorChange($locked, $changes);
                if ($itemChanges['updates'] === [] && $fieldValues === [] && ! $vendorChange['changed']) {
                    throw ValidationException::withMessages([
                        'changes' => 'At least one procurement-controlled value must change.',
                    ]);
                }

                $before = [
                    'items' => $itemChanges['before'],
                    'fields' => $fieldChanges['before'],
                ];
                $after = [
                    'items' => $itemChanges['after'],
                    'fields' => $fieldValues,
                ];
                if ($vendorChange['changed']) {
                    $before['vendor_id'] = $vendorChange['before'];
                    $after['vendor_id'] = $vendorChange['after'];
                }

                if ($itemChanges['before'] !== []) {
                    $before['total_amount'] = (string) $locked->total_amount;
                    $after['total_amount'] = $this->totals->totals($itemChanges['all_after'])['header_total'];
                }

                $this->audit($locked, $reviewer, 'edited', $before, $after, $reason);
                if ($vendorChange['changed']) {
                    PurchaseRequest::query()->withoutGlobalScopes()->whereKey($locked->getKey())->update([
                        'vendor_id' => $vendorChange['after'],
                        'updated_at' => now(),
                    ]);
                }

                foreach ($itemChanges['updates'] as $itemId => $attributes) {
                    $locked->items->findOrFail($itemId)->fill($attributes)->save();
                }

                foreach ($fieldValues as $fieldKey => $value) {
                    $field = $fieldChanges['definitions'][$fieldKey];
                    $existing = $locked->fieldValues->firstWhere('field_id', $field->getKey());
                    $attributes = [
                        'field_key' => $field->key,
                        'field_label' => $field->label,
                        'field_type' => $field->field_type->value,
                        'field_version' => $field->version,
                        'definition_snapshot' => $field->definitionSnapshot(),
                        'value' => $value,
                    ];

                    if ($existing instanceof PurchaseRequestFieldValue) {
                        $existing->fill($attributes)->save();
                    } else {
                        $locked->fieldValues()->create([
                            'field_id' => $field->getKey(),
                            ...$attributes,
                        ]);
                    }
                }

                if ($itemChanges['updates'] !== []) {
                    $locked->syncTotals();
                }

                return $locked->refresh()->load(['items', 'fieldValues']);
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $reviewer->getKey(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(
        PurchaseRequest $request,
        array $changes,
        string $reason,
        ?User $reviewer = null,
    ): PurchaseRequest {
        return $this->edit($request, $changes, $reason, $reviewer);
    }

    /** @return Collection<int, UserAssignment> */
    private function reviewAssignments(User $reviewer): Collection
    {
        return $this->context->allowedAssignments($reviewer)
            ->filter(fn (UserAssignment $assignment): bool => $assignment->allows(ProcurementPermissions::UPDATE))
            ->values();
    }

    private function activeReviewer(?User $reviewer): User
    {
        $reviewer ??= auth()->user();

        if (! $reviewer instanceof User || ! $reviewer->is_active) {
            throw new AuthorizationException('An active authenticated reviewer is required.');
        }

        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_PROCUREMENT_REVIEWS, $reviewer);

        return $reviewer;
    }

    /** @param array<string, mixed> $preview */
    private function assertHandoffPreview(array $preview): void
    {
        if (($preview['can_handoff'] ?? false) === true) {
            return;
        }

        $errors = array_values(array_filter($preview['errors'] ?? [], 'is_string'));
        throw ValidationException::withMessages([
            'workflow' => $errors !== []
                ? 'Required approver configuration is incomplete: '.implode(' ', $errors)
                : 'Required approver configuration is incomplete for this purchase request.',
        ]);
    }

    private function authorizeReview(PurchaseRequest $request, User $reviewer): void
    {
        Gate::forUser($reviewer)->authorize('review', $request);
        $this->assertReviewContext($request, $reviewer);
    }

    private function assertReviewContext(PurchaseRequest $request, User $reviewer): void
    {
        $assignment = $this->context->assignment();
        if ($assignment === null
            || ! $assignment->allows(ProcurementPermissions::UPDATE)
            || (int) $assignment->office_id !== (int) $request->office_id
            || ! $this->assignmentMatchesRequest($assignment, $request)) {
            throw new AuthorizationException('An active procurement review context is required for this purchase request.');
        }

        if (! $reviewer->is(auth()->user())) {
            throw new AuthorizationException('The authenticated reviewer does not match the requested actor.');
        }
    }

    private function assignmentMatchesRequest(UserAssignment $assignment, PurchaseRequest $request): bool
    {
        if ($assignment->branch_id !== null && (int) $assignment->branch_id !== (int) $request->branch_id) {
            return false;
        }

        if ($assignment->department_id !== null && (int) $assignment->department_id !== (int) $request->department_id) {
            return false;
        }

        $categoryIds = $assignment->scopes
            ->where('scope_type', 'category')
            ->pluck('scope_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->all();

        return $categoryIds === [] || in_array((int) $request->category_id, $categoryIds, true);
    }

    /** @return array{0: User, 1: ?string} */
    private function reviewerAndReason(User|string|null $reviewerOrReason, ?User $reviewer): array
    {
        if ($reviewerOrReason instanceof User) {
            return [$this->activeReviewer($reviewerOrReason), null];
        }

        return [$this->activeReviewer($reviewer), is_string($reviewerOrReason) ? $reviewerOrReason : null];
    }

    /** @param array<string, mixed> $changes */
    private function assertAllowedChangeKeys(array $changes): void
    {
        $unknown = array_diff(array_keys($changes), ['items', 'fields', 'vendor_id']);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'changes' => 'Only procurement-controlled item and dynamic field values can be edited during review.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{changed: bool, before: int|null, after: int|null}
     */
    private function prepareVendorChange(PurchaseRequest $request, array $changes): array
    {
        if (! array_key_exists('vendor_id', $changes)) {
            return ['changed' => false, 'before' => null, 'after' => null];
        }

        $vendorId = $changes['vendor_id'];
        if ($vendorId !== null && (! is_numeric($vendorId) || ! Vendor::query()->whereKey((int) $vendorId)->where('is_active', true)->exists())) {
            throw ValidationException::withMessages([
                'vendor_id' => 'The selected vendor must be active.',
            ]);
        }

        $vendorId = $vendorId === null ? null : (int) $vendorId;
        $before = $request->vendor_id === null ? null : (int) $request->vendor_id;

        return [
            'changed' => $before !== $vendorId,
            'before' => $before,
            'after' => $vendorId,
        ];
    }

    /**
     * @return array{before: array<int, array<string, mixed>>, after: array<int, array<string, mixed>>, updates: array<int, array<string, mixed>>, all_after: list<array<string, mixed>>}
     */
    private function prepareItemChanges(PurchaseRequest $request, mixed $rawChanges): array
    {
        if ($rawChanges === null) {
            return ['before' => [], 'after' => [], 'updates' => [], 'all_after' => $request->items->map(fn (PurchaseRequestItem $item): array => $item->toArray())->all()];
        }

        if (! is_array($rawChanges)) {
            throw ValidationException::withMessages(['items' => 'Review item changes must be an array.']);
        }

        $before = [];
        $after = [];
        $updates = [];
        $allAfter = $request->items->mapWithKeys(fn (PurchaseRequestItem $item): array => [$item->getKey() => $item->toArray()])->all();
        $allowedKeys = [...self::EDITABLE_ITEM_FIELDS, 'id', 'item_id', 'purchase_request_item_id', 'negotiated_estimate'];

        foreach ($rawChanges as $key => $rawChange) {
            if (! is_array($rawChange)) {
                throw ValidationException::withMessages(["items.{$key}" => 'Each review item change must be an object.']);
            }

            $itemId = $rawChange['id'] ?? $rawChange['item_id'] ?? $rawChange['purchase_request_item_id'] ?? (is_numeric($key) && ! array_is_list($rawChanges) ? $key : null);
            if (! is_numeric($itemId)) {
                throw ValidationException::withMessages(["items.{$key}.id" => 'A purchase request item id is required for review changes.']);
            }

            $item = $request->items->firstWhere('id', (int) $itemId);
            if (! $item instanceof PurchaseRequestItem) {
                throw ValidationException::withMessages(["items.{$key}.id" => 'The purchase request item is not part of this request.']);
            }

            $unknown = array_diff(array_keys($rawChange), $allowedKeys);
            if ($unknown !== []) {
                throw ValidationException::withMessages(["items.{$key}" => 'The requested item field cannot be changed during review.']);
            }

            $attributes = [];
            foreach (self::EDITABLE_ITEM_FIELDS as $field) {
                if (array_key_exists($field, $rawChange)) {
                    $attributes[$field] = $rawChange[$field];
                }
            }
            if (array_key_exists('negotiated_estimate', $rawChange)) {
                $attributes['unit_price'] = $rawChange['negotiated_estimate'];
            }
            $this->validateItemAttributes($attributes, $key);
            if ($attributes === []) {
                throw ValidationException::withMessages(["items.{$key}" => 'At least one controlled item field is required.']);
            }

            $old = $this->itemSnapshot($item, array_keys($attributes));
            $new = $this->itemSnapshot($item, array_keys($attributes), $attributes);
            if ($old === $new) {
                continue;
            }

            $before[$item->getKey()] = $old;
            $after[$item->getKey()] = $new;
            $updates[$item->getKey()] = $attributes;
            $allAfter[$item->getKey()] = [...$allAfter[$item->getKey()], ...$attributes];
        }

        return [
            'before' => $before,
            'after' => $after,
            'updates' => $updates,
            'all_after' => array_values($allAfter),
        ];
    }

    /**
     * @return array{0: array{before: array<string, mixed>, definitions: array<string, ProcurementField>}, 1: array<string, mixed>}
     */
    private function prepareFieldChanges(PurchaseRequest $request, mixed $rawChanges): array
    {
        if ($rawChanges === null) {
            return [['before' => [], 'definitions' => []], []];
        }

        if (! is_array($rawChanges)) {
            throw ValidationException::withMessages(['fields' => 'Review dynamic field changes must be an array.']);
        }

        $fields = ProcurementField::query()
            ->where('category_id', $request->category_id)
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->keyBy('key');
        $editableFields = $fields->filter(fn (ProcurementField $field): bool => $field->editable_stage === ProcurementField::EDITABLE_STAGE_REVIEW);
        $input = $this->fieldInput($rawChanges);
        $unknown = array_diff(array_keys($input), $editableFields->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['fields' => 'Only dynamic fields configured for procurement review can be changed.']);
        }

        $existing = $request->fieldValues->mapWithKeys(fn (PurchaseRequestFieldValue $value): array => [$value->field_key => $value->value])->all();
        $validated = $this->dynamicFields->validate($editableFields, ['fields' => [...$existing, ...$input]]);
        $validatedValues = is_array($validated['fields'] ?? null) ? $validated['fields'] : $validated;
        $before = [];
        $changes = [];
        $definitions = [];

        foreach ($input as $key => $value) {
            $field = $editableFields->get($key);
            if (! $field instanceof ProcurementField) {
                continue;
            }

            $newValue = $validatedValues[$key] ?? null;
            if ($this->sameValue($existing[$key] ?? null, $newValue)) {
                continue;
            }

            $before[$key] = $existing[$key] ?? null;
            $changes[$key] = $newValue;
            $definitions[$key] = $field;
        }

        return [['before' => $before, 'definitions' => $definitions], $changes];
    }

    /** @return array<string, mixed> */
    private function fieldInput(array $rawChanges): array
    {
        $input = [];
        foreach ($rawChanges as $key => $value) {
            if (is_string($key)) {
                $input[$key] = is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
            } elseif (is_array($value) && isset($value['field_key'])) {
                $input[(string) $value['field_key']] = $value['value'] ?? null;
            }
        }

        return $input;
    }

    /** @param array<string, mixed> $attributes */
    private function validateItemAttributes(array $attributes, int|string $key): void
    {
        if (array_key_exists('quantity', $attributes)
            && (! is_numeric($attributes['quantity']) || (float) $attributes['quantity'] <= 0)) {
            throw ValidationException::withMessages(["items.{$key}.quantity" => 'Quantity must be greater than zero.']);
        }

        if (array_key_exists('unit_price', $attributes)
            && (! is_numeric($attributes['unit_price']) || (float) $attributes['unit_price'] < 0)) {
            throw ValidationException::withMessages(["items.{$key}.unit_price" => 'The negotiated estimate must be zero or greater.']);
        }

        if (array_key_exists('specifications', $attributes)
            && $attributes['specifications'] !== null
            && ! is_array($attributes['specifications'])) {
            throw ValidationException::withMessages(["items.{$key}.specifications" => 'Specifications must be an object.']);
        }
    }

    /** @param list<string> $fields */
    private function itemSnapshot(PurchaseRequestItem $item, array $fields, array $updates = []): array
    {
        $snapshot = [];
        foreach ($fields as $field) {
            $value = array_key_exists($field, $updates) ? $updates[$field] : $item->getAttribute($field);
            if (in_array($field, ['quantity', 'unit_price'], true) && is_numeric($value)) {
                $value = number_format((float) $value, 2, '.', '');
            }
            $snapshot[$field] = $value;
        }

        return $snapshot;
    }

    private function sameValue(mixed $before, mixed $after): bool
    {
        return json_encode($before, JSON_THROW_ON_ERROR) === json_encode($after, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function audit(
        PurchaseRequest $request,
        User $actor,
        string $event,
        array $before,
        array $after,
        ?string $reason,
    ): void {
        $assignment = $this->context->assignment();
        $context = $this->context->snapshot();
        $role = $assignment?->assignedRole?->name ?? $this->context->roleName();

        activity('procurement')
            ->performedOn($request)
            ->causedBy($actor)
            ->event('review_'.$event)
            ->withProperties([
                'before' => $before,
                'after' => $after,
                'actor_id' => $actor->getKey(),
                'role_id' => $context['role_id'] ?? null,
                'role' => $role,
                'office_id' => $context['office_id'] ?? $request->office_id,
                'branch_id' => $context['branch_id'] ?? null,
                'department_id' => $context['department_id'] ?? null,
                'timestamp' => now()->toISOString(),
                'reason' => $reason,
                'access_context' => $context,
            ])
            ->log('Purchase request procurement review '.$event);
    }
}
