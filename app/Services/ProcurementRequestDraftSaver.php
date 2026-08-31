<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CostCenter;
use App\Models\DepartureBatch;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ProcurementRequestDraftSaver
{
    public function __construct(
        private readonly AccessContextService $context,
        private readonly PurchaseRequestTotalCalculator $totals,
        private readonly DynamicFieldValidator $dynamicFields,
        private readonly AttachmentService $attachments,
        private readonly DomainTransaction $transaction,
    ) {}

    public function save(array $data, ?PurchaseRequest $request = null, ?User $user = null): PurchaseRequest
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->is_active) {
            throw new AuthorizationException('An active authenticated user is required.');
        }

        $assignment = $this->context->assignment();
        if ($assignment === null) {
            throw new AuthorizationException('An active office context is required.');
        }

        $request === null
            ? Gate::forUser($user)->authorize('create', PurchaseRequest::class)
            : Gate::forUser($user)->authorize('update', $request);

        if ($request !== null && ! $request->isDraft()) {
            throw new AuthorizationException('Only draft purchase requests can be edited.');
        }

        $lines = $this->prepareLines($data['items'] ?? []);
        $dynamicValues = $this->prepareDynamicValues($data, $request);
        $attributes = $this->prepareAttributes(
            $data,
            $assignment->office_id,
            $assignment->branch_id,
            $assignment->department_id,
            $request,
            $user,
        );
        $attachments = $this->uploadedFiles($data['attachments'] ?? []);

        return $this->transaction->run(
            'save purchase request draft',
            function () use ($request, $attributes, $lines, $dynamicValues, $attachments, $user): PurchaseRequest {
                $request ??= new PurchaseRequest;
                $request->fill($attributes);
                $request->status = PurchaseRequest::STATUS_DRAFT;
                $request->save();

                $request->items()->delete();
                foreach ($lines as $line) {
                    $request->items()->create($line);
                }

                $request->fieldValues()->delete();
                foreach ($dynamicValues as $value) {
                    $request->fieldValues()->create($value);
                }

                foreach ($attachments as $file) {
                    $this->attachments->store($file, $request, $user, 'purchase-requests');
                }

                $request->syncTotals();

                return $request->refresh();
            },
            [
                'purchase_request_id' => $request?->getKey(),
                'office_id' => $attributes['office_id'],
                'permission' => $request === null ? ProcurementPermissions::CREATE : ProcurementPermissions::UPDATE,
            ],
        );
    }

    /**
     * @param  array<int|string, mixed>  $rawLines
     * @return list<array<string, mixed>>
     */
    private function prepareLines(mixed $rawLines): array
    {
        if (! is_array($rawLines)) {
            throw ValidationException::withMessages(['items' => 'Items must be an array.']);
        }

        $prepared = [];
        foreach (array_values($rawLines) as $sortOrder => $rawLine) {
            if (! is_array($rawLine)) {
                throw ValidationException::withMessages(["items.{$sortOrder}" => 'Each item line must be an object.']);
            }

            $quantity = $rawLine['quantity'] ?? null;
            $unitPrice = $rawLine['unit_price'] ?? $rawLine['estimated_price'] ?? 0;
            $lineTotal = $this->totals->lineTotal($quantity, $unitPrice);

            $prepared[] = [
                'procurement_item_id' => $this->nullableInteger($rawLine['procurement_item_id'] ?? $rawLine['item_id'] ?? null),
                'procurement_unit_id' => $this->nullableInteger($rawLine['procurement_unit_id'] ?? $rawLine['unit_id'] ?? null),
                'procurement_variant_id' => $this->nullableInteger($rawLine['procurement_variant_id'] ?? $rawLine['variant_id'] ?? null),
                'item_name' => $rawLine['item_name'] ?? $rawLine['name'] ?? null,
                'description' => $rawLine['description'] ?? null,
                'unit_name' => $rawLine['unit_name'] ?? null,
                'variant_name' => $rawLine['variant_name'] ?? null,
                'variant_value' => $rawLine['variant_value'] ?? null,
                'specifications' => is_array($rawLine['specifications'] ?? null) ? $rawLine['specifications'] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'notes' => $rawLine['notes'] ?? null,
                'sort_order' => $sortOrder,
            ];
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function prepareDynamicValues(array $data, ?PurchaseRequest $request): array
    {
        $rawValues = $data['fields'] ?? $data['dynamic_fields'] ?? [];
        if (! is_array($rawValues) || $rawValues === []) {
            return [];
        }

        $categoryId = $data['category_id'] ?? $request?->category_id;
        if (! is_numeric($categoryId)) {
            return [];
        }

        $fields = ProcurementField::query()
            ->where('category_id', (int) $categoryId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $valuesByKey = $this->valuesByKey($rawValues);
        $matchingFields = $fields->filter(fn (ProcurementField $field): bool => array_key_exists($field->key, $valuesByKey));
        if ($matchingFields->isEmpty()) {
            return [];
        }

        $validated = $this->dynamicFields->validate($matchingFields, ['fields' => $valuesByKey]);
        $validatedValues = is_array($validated['fields'] ?? null) ? $validated['fields'] : $validated;

        return $matchingFields
            ->filter(fn (ProcurementField $field): bool => array_key_exists($field->key, $validatedValues))
            ->map(fn (ProcurementField $field): array => [
                'field_id' => $field->getKey(),
                'field_key' => $field->key,
                'field_label' => $field->label,
                'field_type' => $field->field_type->value,
                'field_version' => $field->version,
                'definition_snapshot' => $field->definitionSnapshot(),
                'value' => $validatedValues[$field->key],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<string, mixed>
     */
    private function valuesByKey(array $values): array
    {
        $keyed = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $keyed[$key] = $value;

                continue;
            }

            if (is_array($value) && isset($value['field_key'])) {
                $keyed[(string) $value['field_key']] = $value['value'] ?? null;
            }
        }

        return $keyed;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAttributes(
        array $data,
        int $officeId,
        ?int $branchId,
        ?int $departmentId,
        ?PurchaseRequest $request,
        User $user,
    ): array {
        $costCenterId = $this->nullableInteger($data['cost_center_id'] ?? $request?->cost_center_id);
        if ($costCenterId !== null && ! CostCenter::withoutGlobalScope('access_context')
            ->whereKey($costCenterId)
            ->where('office_id', $officeId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'cost_center_id' => 'The cost center must belong to the active office and be active.',
            ]);
        }

        $categoryId = $this->nullableInteger($data['category_id'] ?? $request?->category_id);
        if ($categoryId !== null && ! ProcurementCategory::query()
            ->availableForNewPurchaseRequests()
            ->whereKey($categoryId)
            ->exists()) {
            throw ValidationException::withMessages(['category_id' => 'The selected category is inactive or unavailable.']);
        }

        $batchId = $this->nullableInteger($data['departure_batch_id'] ?? $data['batch_id'] ?? $request?->departure_batch_id);
        if ($batchId !== null && ! DepartureBatch::query()
            ->whereKey($batchId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['departure_batch_id' => 'The selected batch is inactive or unavailable.']);
        }

        $priority = (string) ($data['priority'] ?? $request?->priority ?? 'normal');

        return [
            'office_id' => $officeId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'cost_center_id' => $costCenterId,
            'departure_batch_id' => $this->nullableInteger($data['departure_batch_id'] ?? $data['batch_id'] ?? $request?->departure_batch_id),
            'requester_id' => $request?->requester_id ?? $user->getKey(),
            'category_id' => $this->nullableInteger($data['category_id'] ?? $request?->category_id),
            'title' => $data['title'] ?? $request?->title,
            'notes' => $data['notes'] ?? $request?->notes,
            'reason' => $data['reason'] ?? $request?->reason,
            'required_date' => $data['required_date'] ?? $data['need_date'] ?? $request?->required_date,
            'priority' => $priority,
            'status' => PurchaseRequest::STATUS_DRAFT,
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (is_numeric($value) ? (int) $value : null);
    }
}
