<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationRecommendation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\DomainTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class QuotationComparisonService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly PurchaseRequestTotalCalculator $totals,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * Return a server-calculated comparison for all quotations on a PR.
     *
     * @return array{purchase_request_id:int, lines:list<array<string,mixed>>, quotations:list<array<string,mixed>>, overall_totals:array<int|string,string>}
     */
    public function compare(PurchaseRequest $request, ?User $actor = null): array
    {
        $this->authorizeView($request, $actor);
        $request->loadMissing(['items', 'quotations.vendor', 'quotations.items.purchaseRequestItem']);
        $requestItems = $request->items()->get();
        $requestItemIds = $requestItems->modelKeys();
        $quotations = $request->quotations()->with(['vendor', 'items.purchaseRequestItem'])->get();

        $quotationRows = [];
        $lineRows = [];
        foreach ($requestItems as $requestItem) {
            $lineRows[$requestItem->getKey()] = [
                'purchase_request_item_id' => $requestItem->getKey(),
                'item_name' => $requestItem->item_name,
                'description' => $requestItem->description,
                'quantity' => (string) $requestItem->quantity,
                'unit_name' => $requestItem->unit_name,
                'quotations' => [],
            ];
        }

        foreach ($quotations as $quotation) {
            $coverage = $this->coverage($quotation, $requestItemIds);
            $lineTotals = [];
            $linePrices = [];

            foreach ($quotation->items as $item) {
                $lineTotal = $this->totals->lineTotal($item->quantity, $item->unit_price);
                $lineTotals[$item->purchase_request_item_id] = $lineTotal;
                $linePrices[$item->purchase_request_item_id] = [
                    'quotation_item_id' => $item->getKey(),
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => $lineTotal,
                    'notes' => $item->notes,
                ];

                if (isset($lineRows[$item->purchase_request_item_id])) {
                    $lineRows[$item->purchase_request_item_id]['quotations'][$quotation->getKey()] = $linePrices[$item->purchase_request_item_id];
                }
            }

            $subtotal = $this->sum($lineTotals);
            $total = $this->overallTotal(
                $subtotal,
                $quotation->discount_amount,
                $quotation->tax_amount,
                $quotation->shipping_amount,
            );
            $quotationRows[] = [
                'id' => $quotation->getKey(),
                'vendor_id' => $quotation->vendor_id,
                'vendor_name' => $quotation->vendor?->name,
                'quotation_number' => $quotation->quotation_number,
                'currency' => $quotation->currency,
                'lines' => $linePrices,
                'line_totals' => $lineTotals,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $this->money($quotation->discount_amount),
                'tax_amount' => $this->money($quotation->tax_amount),
                'shipping_amount' => $this->money($quotation->shipping_amount),
                'total_amount' => $total,
                'stored_total_amount' => (string) $quotation->total_amount,
                'coverage' => $coverage,
                'attachments_count' => $quotation->attachments()->count(),
                'notes' => $quotation->notes,
            ];
        }

        return [
            'purchase_request_id' => (int) $request->getKey(),
            'lines' => array_values($lineRows),
            'quotations' => $quotationRows,
            'overall_totals' => collect($quotationRows)->mapWithKeys(
                fn (array $quotation): array => [$quotation['id'] => $quotation['total_amount']],
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function comparison(PurchaseRequest $request, ?User $actor = null): array
    {
        return $this->compare($request, $actor);
    }

    /**
     * Record one quotation and its mapped line prices without trusting client totals.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordQuotation(
        PurchaseRequest $request,
        array $data,
        ?User $actor = null,
    ): Quotation {
        $actor = $this->activeActor($actor);
        Gate::forUser($actor)->authorize('create', Quotation::class);
        $this->assertAuthenticatedActor($actor);

        $request = PurchaseRequest::query()->withoutGlobalScopes()->findOrFail($request->getKey());
        $this->assertRequestScope($request, $actor);
        $this->validateQuotationData($request, $data);
        $lines = $this->normaliseLines($request, $data['items'] ?? []);
        $files = $this->uploadedFiles($data['attachments'] ?? []);
        $attributes = [
            'purchase_request_id' => $request->getKey(),
            'vendor_id' => (int) $data['vendor_id'],
            'created_by_id' => $actor->getKey(),
            'quotation_number' => trim((string) $data['quotation_number']),
            'quoted_at' => $data['quoted_at'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'IDR')),
            'discount_amount' => $this->money($data['discount_amount'] ?? 0),
            'tax_amount' => $this->money($data['tax_amount'] ?? 0),
            'shipping_amount' => $this->money($data['shipping_amount'] ?? 0),
            'status' => Quotation::STATUS_SUBMITTED,
            'notes' => $data['notes'] ?? null,
            'submitted_at' => now(),
        ];

        return $this->transaction->run(
            'record vendor quotation',
            function () use ($attributes, $lines, $files, $actor): Quotation {
                $quotation = Quotation::create($attributes);
                foreach ($lines as $line) {
                    $quotation->items()->create($line);
                }
                $quotation->load('items');
                $quotation->syncCalculatedTotals();

                foreach ($files as $file) {
                    $this->attachments->store($file, $quotation, $actor, 'quotation');
                }

                return $quotation->fresh(['vendor', 'items', 'attachments']);
            },
            [
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $actor->getKey(),
            ],
        );
    }

    /** @param array<string, mixed> $data */
    public function createQuotation(PurchaseRequest $request, array $data, ?User $actor = null): Quotation
    {
        return $this->recordQuotation($request, $data, $actor);
    }

    /**
     * Update a quotation's commercial details and mapped lines.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateQuotation(
        Quotation $quotation,
        array $data,
        ?User $actor = null,
    ): Quotation {
        $actor = $this->activeActor($actor);
        Gate::forUser($actor)->authorize('update', $quotation);
        $this->assertAuthenticatedActor($actor);
        $request = PurchaseRequest::query()->withoutGlobalScopes()->findOrFail($quotation->purchase_request_id);
        $this->assertRequestScope($request, $actor);
        $this->validateQuotationData($request, [
            ...$data,
            'vendor_id' => $data['vendor_id'] ?? $quotation->vendor_id,
            'quotation_number' => $data['quotation_number'] ?? $quotation->quotation_number,
        ]);
        $lines = $this->normaliseLines($request, $data['items'] ?? []);
        $files = $this->uploadedFiles($data['attachments'] ?? []);

        return $this->transaction->run(
            'update vendor quotation',
            function () use ($quotation, $data, $lines, $files, $actor): Quotation {
                $quotation->update([
                    'vendor_id' => (int) ($data['vendor_id'] ?? $quotation->vendor_id),
                    'quotation_number' => trim((string) ($data['quotation_number'] ?? $quotation->quotation_number)),
                    'quoted_at' => $data['quoted_at'] ?? null,
                    'valid_until' => $data['valid_until'] ?? null,
                    'currency' => strtoupper((string) ($data['currency'] ?? 'IDR')),
                    'discount_amount' => $this->money($data['discount_amount'] ?? 0),
                    'tax_amount' => $this->money($data['tax_amount'] ?? 0),
                    'shipping_amount' => $this->money($data['shipping_amount'] ?? 0),
                    'notes' => $data['notes'] ?? null,
                ]);
                $quotation->items()->delete();
                foreach ($lines as $line) {
                    $quotation->items()->create($line);
                }
                $quotation->load('items');
                $quotation->syncCalculatedTotals();
                foreach ($files as $file) {
                    $this->attachments->store($file, $quotation, $actor, 'quotation');
                }

                return $quotation->fresh(['vendor', 'items', 'attachments']);
            },
            [
                'quotation_id' => $quotation->getKey(),
                'purchase_request_id' => $request->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $actor->getKey(),
            ],
        );
    }

    /**
     * Persist a new immutable recommendation version and update only the PR's
     * recommendation pointer. PR financial facts remain unchanged.
     *
     * @param  array<int, int|string>|User|null  $evidenceOrActor
     */
    public function recommend(
        PurchaseRequest $request,
        Quotation|int $quotation,
        string $reason,
        array|User|null $evidenceOrActor = null,
        ?User $actor = null,
    ): QuotationRecommendation {
        if ($evidenceOrActor instanceof User) {
            $actor = $evidenceOrActor;
            $evidenceAttachmentIds = null;
        } else {
            $evidenceAttachmentIds = $evidenceOrActor;
        }

        $actor = $this->activeActor($actor);
        $this->assertAuthenticatedActor($actor);
        $this->assertRequestScope($request, $actor);
        $quotation = $quotation instanceof Quotation
            ? Quotation::query()->withoutGlobalScopes()->with(['vendor', 'items', 'attachments'])->findOrFail($quotation->getKey())
            : Quotation::query()->withoutGlobalScopes()->with(['vendor', 'items', 'attachments'])->findOrFail($quotation);
        Gate::forUser($actor)->authorize('recommend', $quotation);

        if ((int) $quotation->purchase_request_id !== (int) $request->getKey()) {
            throw ValidationException::withMessages([
                'quotation' => 'The quotation does not belong to this purchase request.',
            ]);
        }

        $request = PurchaseRequest::query()->withoutGlobalScopes()->with(['items', 'category'])->findOrFail($request->getKey());
        $coverage = $this->coverage($quotation, $request->items()->pluck('id')->all());
        if (! $coverage['complete']) {
            throw ValidationException::withMessages([
                'quotation' => 'Every purchase request item must be covered before a vendor can be recommended.',
                'coverage' => $coverage,
            ]);
        }

        $configuration = $request->category?->configuration();
        $reason = trim($reason);
        if ($configuration?->requiresRecommendationReason() && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A recommendation reason is required for this procurement category.',
            ]);
        }

        $evidenceAttachmentIds ??= $quotation->attachments->pluck('id')->all();
        $evidenceAttachmentIds = array_values(array_unique(array_map('intval', $evidenceAttachmentIds)));
        $quotationAttachmentIds = $quotation->attachments->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        if (array_diff($evidenceAttachmentIds, $quotationAttachmentIds) !== []) {
            throw ValidationException::withMessages([
                'evidence' => 'Recommendation evidence must belong to the selected quotation.',
            ]);
        }

        if ($configuration?->requiresRecommendationEvidence() && $evidenceAttachmentIds === []) {
            throw ValidationException::withMessages([
                'evidence' => 'At least one quotation attachment is required as recommendation evidence.',
            ]);
        }

        $comparison = $this->compare($request);
        $selected = collect($comparison['quotations'])->firstWhere('id', $quotation->getKey());
        $reason = $reason !== '' ? $reason : 'Recommendation recorded without additional justification.';
        $recommendation = $this->transaction->run(
            'recommend quotation vendor',
            function () use ($request, $quotation, $actor, $reason, $evidenceAttachmentIds, $selected): QuotationRecommendation {
                $latestVersion = (int) QuotationRecommendation::query()
                    ->where('purchase_request_id', $request->getKey())
                    ->lockForUpdate()
                    ->max('version');
                $version = $latestVersion + 1;
                $recommendation = QuotationRecommendation::create([
                    'purchase_request_id' => $request->getKey(),
                    'quotation_id' => $quotation->getKey(),
                    'vendor_id' => $quotation->vendor_id,
                    'recommended_by_id' => $actor->getKey(),
                    'office_id' => $request->office_id,
                    'version' => $version,
                    'reason' => $reason,
                    'evidence_attachment_ids' => $evidenceAttachmentIds,
                    'comparison_snapshot' => $selected,
                ]);

                PurchaseRequest::query()->withoutGlobalScopes()->whereKey($request->getKey())->update([
                    'recommended_quotation_id' => $quotation->getKey(),
                    'recommendation_reason' => $reason,
                    'recommendation_version' => $version,
                    'recommended_at' => now(),
                    'recommended_by_id' => $actor->getKey(),
                    'updated_at' => now(),
                ]);

                activity('procurement')
                    ->performedOn($request)
                    ->causedBy($actor)
                    ->event('quotation_recommended')
                    ->withProperties([
                        'quotation_id' => $quotation->getKey(),
                        'vendor_id' => $quotation->vendor_id,
                        'version' => $version,
                        'reason' => $reason,
                        'evidence_attachment_ids' => $evidenceAttachmentIds,
                        'comparison_snapshot' => $selected,
                        'purchase_request_total_amount' => (string) $request->total_amount,
                        'purchase_request_vendor_id' => $request->vendor_id,
                    ])
                    ->log('Vendor quotation recommended');

                return $recommendation;
            },
            [
                'purchase_request_id' => $request->getKey(),
                'quotation_id' => $quotation->getKey(),
                'office_id' => $request->office_id,
                'actor_id' => $actor->getKey(),
            ],
        );

        return $recommendation->fresh(['quotation.vendor', 'recommendedBy', 'attachments']);
    }

    public function saveRecommendation(
        PurchaseRequest $request,
        Quotation|int $quotation,
        string $reason,
        array|User|null $evidenceOrActor = null,
        ?User $actor = null,
    ): QuotationRecommendation {
        return $this->recommend($request, $quotation, $reason, $evidenceOrActor, $actor);
    }

    public function handoffToApproval(PurchaseRequest $request, ?User $actor = null): QuotationRecommendation
    {
        $actor = $this->activeActor($actor);
        $this->assertAuthenticatedActor($actor);
        $request = PurchaseRequest::query()
            ->withoutGlobalScopes()
            ->with(['category', 'items'])
            ->findOrFail($request->getKey());
        $this->assertRequestScope($request, $actor);
        $recommendation = $request->quotationRecommendations()
            ->with(['quotation.vendor', 'quotation.items', 'quotation.attachments'])
            ->latest('version')
            ->first();

        if (! $recommendation instanceof QuotationRecommendation) {
            throw ValidationException::withMessages([
                'recommendation' => 'A quotation recommendation is required before handoff.',
            ]);
        }

        Gate::forUser($actor)->authorize('recommend', $recommendation->quotation);

        if (! $this->coverage($recommendation->quotation, $request->items->pluck('id')->all())['complete']) {
            throw ValidationException::withMessages([
                'quotation' => 'The recommended quotation must cover every purchase request item.',
            ]);
        }

        $configuration = $request->category?->configuration();
        if ($configuration?->requiresRecommendationReason() && blank($recommendation->reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A recommendation reason is required before handoff.',
            ]);
        }

        $evidenceAttachmentIds = array_map('intval', $recommendation->evidence_attachment_ids ?? []);
        $quotationAttachmentIds = array_map('intval', $recommendation->quotation->attachments->modelKeys());
        if ($configuration?->requiresRecommendationEvidence()
            && ($evidenceAttachmentIds === [] || array_diff($evidenceAttachmentIds, $quotationAttachmentIds) !== [])) {
            throw ValidationException::withMessages([
                'evidence' => 'Recommendation evidence must remain attached to the quotation before handoff.',
            ]);
        }

        activity('procurement')
            ->performedOn($request)
            ->causedBy($actor)
            ->event('quotation_handoff')
            ->withProperties([
                'recommendation_id' => $recommendation->getKey(),
                'recommendation_version' => $recommendation->version,
            ])
            ->log('Quotation recommendation handed off for approval');

        return $recommendation->fresh(['quotation.vendor']);
    }

    public function forwardToApproval(PurchaseRequest $request, ?User $actor = null): QuotationRecommendation
    {
        return $this->handoffToApproval($request, $actor);
    }

    /** @return array{missing:list<int>, extra:list<int>, complete:bool} */
    public function validateLineCoverage(Quotation $quotation, ?PurchaseRequest $request = null): array
    {
        $request ??= $quotation->purchaseRequest()->withoutGlobalScopes()->firstOrFail();

        return $this->coverage($quotation, $request->items()->pluck('id')->all());
    }

    /** @param array<string, mixed> $data */
    private function validateQuotationData(PurchaseRequest $request, array $data): void
    {
        if (blank($data['quotation_number'] ?? null)) {
            throw ValidationException::withMessages(['quotation_number' => 'A quotation number is required.']);
        }

        if (! is_numeric($data['vendor_id'] ?? null)
            || ! Vendor::query()->availableForNewTransactions()->whereKey((int) $data['vendor_id'])->exists()) {
            throw ValidationException::withMessages(['vendor_id' => 'The selected vendor is inactive or invalid.']);
        }

        if (! $request->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'A purchase request must contain at least one item.']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function normaliseLines(PurchaseRequest $request, mixed $rawLines): array
    {
        if (! is_array($rawLines)) {
            throw ValidationException::withMessages(['items' => 'Quotation items must be an array.']);
        }

        $requestItems = $request->items()->get()->keyBy('id');
        $lines = [];
        foreach (array_values($rawLines) as $sortOrder => $rawLine) {
            if (! is_array($rawLine) || ! is_numeric($rawLine['purchase_request_item_id'] ?? null)) {
                throw ValidationException::withMessages(["items.{$sortOrder}" => 'Each quotation line must map to a purchase request item.']);
            }

            $requestItemId = (int) $rawLine['purchase_request_item_id'];
            $requestItem = $requestItems->get($requestItemId);
            if (! $requestItem instanceof PurchaseRequestItem) {
                throw ValidationException::withMessages(["items.{$sortOrder}" => 'The quotation line is outside the purchase request.']);
            }

            $quantity = $rawLine['quantity'] ?? $requestItem->quantity;
            $unitPrice = $rawLine['unit_price'] ?? null;
            if ($unitPrice === null) {
                throw ValidationException::withMessages(["items.{$sortOrder}.unit_price" => 'A quoted unit price is required.']);
            }

            $this->totals->lineTotal($quantity, $unitPrice);
            $lines[] = [
                'purchase_request_item_id' => $requestItemId,
                'description' => $rawLine['description'] ?? $requestItem->description,
                'specifications' => is_array($rawLine['specifications'] ?? null) ? $rawLine['specifications'] : $requestItem->specifications,
                'quantity' => $quantity,
                'unit_name' => $rawLine['unit_name'] ?? $requestItem->unit_name,
                'unit_price' => $unitPrice,
                'notes' => $rawLine['notes'] ?? null,
                'sort_order' => $sortOrder,
            ];
        }

        return $lines;
    }

    /** @return array{missing:list<int>, extra:list<int>, complete:bool} */
    private function coverage(Quotation $quotation, array $requestItemIds): array
    {
        $quotedItemIds = $quotation->items->pluck('purchase_request_item_id')->map(fn (mixed $id): int => (int) $id)->all();
        $requestItemIds = array_values(array_unique(array_map('intval', $requestItemIds)));
        $missing = array_values(array_diff($requestItemIds, $quotedItemIds));
        $extra = array_values(array_diff($quotedItemIds, $requestItemIds));

        $duplicates = count($quotedItemIds) !== count(array_unique($quotedItemIds));
        $complete = $missing === [] && $extra === [] && ! $duplicates;

        return ['missing' => $missing, 'extra' => $extra, 'complete' => $complete];
    }

    private function overallTotal(mixed $subtotal, mixed $discount, mixed $tax, mixed $shipping): string
    {
        $total = bcsub($this->money($subtotal), $this->money($discount), 2);
        $total = bcadd($total, $this->money($tax), 2);

        return bcadd($total, $this->money($shipping), 2);
    }

    /** @param array<int|string, string> $values */
    private function sum(array $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, $value, 2);
        }

        return $total;
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1) {
            throw ValidationException::withMessages(['amount' => 'Quotation monetary values must be non-negative decimals.']);
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    /** @return list<UploadedFile> */
    private function uploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }
        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active) {
            throw new AuthorizationException('An active authenticated procurement user is required.');
        }

        return $actor;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        if (! $actor->is(auth()->user())) {
            throw new AuthorizationException('The authenticated procurement user does not match the requested actor.');
        }
    }

    private function authorizeView(PurchaseRequest $request, ?User $actor): void
    {
        $actor ??= auth()->user();
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('view', $request);
            $this->assertRequestScope($request, $actor);
        }
    }

    private function assertRequestScope(PurchaseRequest $request, User $actor): void
    {
        if (! app(MultiOfficeAuthorization::class)->canView($actor, $request)) {
            throw new AuthorizationException('The purchase request is outside the active procurement scope.');
        }
    }
}
