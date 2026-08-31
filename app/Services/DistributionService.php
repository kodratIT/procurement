<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainMutationException;
use App\Models\Attachment;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Pilgrim;
use App\Models\PilgrimDistributionItem;
use App\Models\ProcurementItem;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class DistributionService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(UmrahBatch|int $batch, array $data, ?User $actor = null): Distribution
    {
        $actor = $this->activeActor($actor);
        $batchId = $batch instanceof UmrahBatch ? (int) $batch->getKey() : $this->positiveInteger($batch, 'umrah_batch_id');

        return $this->transaction->run('record batch distribution', function () use ($batchId, $data, $actor): Distribution {
            $lockedBatch = UmrahBatch::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($batchId);
            $this->authorization->authorizeMutation($actor, $lockedBatch, ProcurementPermissions::CREATE);
            if (! $lockedBatch->is_active) {
                throw ValidationException::withMessages(['umrah_batch_id' => 'An inactive Umrah batch cannot receive distributions.']);
            }

            $mode = $this->receiptMode($data['receipt_mode'] ?? $data['mode'] ?? null);
            $lines = $this->validatedLines($data['lines'] ?? null);
            $itemIds = array_column($lines, 'procurement_item_id');
            $this->lockStockRows($itemIds);
            $available = $this->availableQuantities($itemIds, (int) $lockedBatch->office_id);
            foreach ($lines as $index => $line) {
                $remaining = $available[$line['procurement_item_id']] ?? '0.00';
                if (bccomp($line['quantity'], $remaining, 2) > 0) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => sprintf('Distribution quantity exceeds available received quantity of %s.', $remaining)]);
                }
            }

            $distribution = Distribution::create([
                'umrah_batch_id' => $lockedBatch->getKey(),
                'distributed_at' => $this->date($data['distributed_at'] ?? $data['date'] ?? null),
                'receipt_mode' => $mode,
                'status' => $data['status'] ?? Distribution::STATUS_RECORDED,
            ]);
            foreach ($lines as $line) {
                $distribution->items()->create($line);
            }

            return $distribution->fresh(['batch', 'items.procurementItem']);
        }, ['umrah_batch_id' => $batchId, 'actor_id' => $actor->getKey()]);
    }

    /**
     * Create or update one pilgrim's allocation and receipt status atomically.
     *
     * Rejected quantities remain allocation history but are deliberately
     * excluded from the received total.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPilgrimReceipt(
        DistributionItem|int $distributionItem,
        Pilgrim|int $pilgrim,
        array $data,
        ?User $actor = null,
    ): PilgrimDistributionItem {
        $actor = $this->activeActor($actor);
        $itemId = $distributionItem instanceof DistributionItem
            ? (int) $distributionItem->getKey()
            : $this->positiveInteger($distributionItem, 'distribution_item_id');
        $pilgrimId = $pilgrim instanceof Pilgrim
            ? (int) $pilgrim->getKey()
            : $this->positiveInteger($pilgrim, 'pilgrim_id');

        try {
            return $this->transaction->run(
                'record pilgrim distribution receipt',
                function () use ($itemId, $pilgrimId, $data, $actor): PilgrimDistributionItem {
                    $item = DistributionItem::query()->lockForUpdate()->findOrFail($itemId);
                    $distribution = Distribution::query()->lockForUpdate()->findOrFail($item->distribution_id);
                    $batch = UmrahBatch::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($distribution->umrah_batch_id);
                    $this->authorization->authorizeMutation($actor, $batch, ProcurementPermissions::RECEIVE);
                    $this->assertIndividualDistribution($distribution, $batch);

                    $subjectPilgrim = Pilgrim::query()
                        ->withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail($pilgrimId);
                    $this->assertPilgrimInScope($subjectPilgrim, $batch);

                    $this->lockStockRows([(int) $item->procurement_item_id]);
                    $this->assertItemAvailability($item, $batch);
                    $existing = PilgrimDistributionItem::query()
                        ->where('distribution_item_id', $item->getKey())
                        ->lockForUpdate()
                        ->get();
                    $allocation = $existing->firstWhere('pilgrim_id', $subjectPilgrim->getKey());
                    $quantity = array_key_exists('quantity', $data)
                        ? $this->quantity($data['quantity'], 'quantity')
                        : ($allocation instanceof PilgrimDistributionItem ? (string) $allocation->quantity : null);
                    if ($quantity === null) {
                        throw ValidationException::withMessages(['quantity' => 'A positive pilgrim receipt quantity is required.']);
                    }
                    $status = $this->pilgrimStatus(
                        $data['status'] ?? ($allocation instanceof PilgrimDistributionItem ? $allocation->status : null),
                    );

                    $this->assertAllocationTotals($existing, $allocation, $quantity, $status, $item);

                    if (! $allocation instanceof PilgrimDistributionItem) {
                        $allocation = new PilgrimDistributionItem([
                            'distribution_item_id' => $item->getKey(),
                            'pilgrim_id' => $subjectPilgrim->getKey(),
                        ]);
                    }
                    $allocation->forceFill(['quantity' => $quantity, 'status' => $status])->save();

                    foreach ($this->pilgrimEvidence($data) as $evidence) {
                        $this->storePilgrimEvidence($allocation, $evidence, $actor);
                    }

                    return $allocation->fresh(['distributionItem.distribution.batch', 'pilgrim', 'attachments']);
                },
                ['distribution_item_id' => $itemId, 'pilgrim_id' => $pilgrimId, 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException) {
                throw $previous;
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function updatePilgrimReceipt(
        DistributionItem|int $distributionItem,
        Pilgrim|int $pilgrim,
        array $data,
        ?User $actor = null,
    ): PilgrimDistributionItem {
        return $this->recordPilgrimReceipt($distributionItem, $pilgrim, $data, $actor);
    }

    /** @param array<string, mixed> $data */
    public function confirmPilgrimReceipt(
        DistributionItem|int $distributionItem,
        Pilgrim|int $pilgrim,
        array $data = [],
        ?User $actor = null,
    ): PilgrimDistributionItem {
        return $this->recordPilgrimReceipt(
            $distributionItem,
            $pilgrim,
            [...$data, 'status' => PilgrimDistributionItem::STATUS_RECEIVED],
            $actor,
        );
    }

    /** @param array<string, mixed> $data */
    public function rejectPilgrimReceipt(
        DistributionItem|int $distributionItem,
        Pilgrim|int $pilgrim,
        array $data = [],
        ?User $actor = null,
    ): PilgrimDistributionItem {
        return $this->recordPilgrimReceipt(
            $distributionItem,
            $pilgrim,
            [...$data, 'status' => PilgrimDistributionItem::STATUS_REJECTED],
            $actor,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function attachPilgrimEvidence(
        PilgrimDistributionItem|int $allocation,
        UploadedFile $file,
        string $type = 'photo',
        array $metadata = [],
        ?User $actor = null,
    ): Attachment {
        $actor = $this->activeActor($actor);
        $allocationId = $allocation instanceof PilgrimDistributionItem
            ? (int) $allocation->getKey()
            : $this->positiveInteger($allocation, 'pilgrim_distribution_item_id');

        try {
            return $this->transaction->run(
                'attach pilgrim distribution evidence',
                function () use ($allocationId, $file, $type, $metadata, $actor): Attachment {
                    $locked = PilgrimDistributionItem::query()->lockForUpdate()->findOrFail($allocationId);
                    $locked->loadMissing('distributionItem.distribution');
                    $item = $locked->distributionItem;
                    if (! $item instanceof DistributionItem) {
                        throw ValidationException::withMessages(['allocation' => 'A valid distribution allocation is required.']);
                    }
                    $item = DistributionItem::query()->lockForUpdate()->findOrFail($item->getKey());
                    $distribution = Distribution::query()->lockForUpdate()->findOrFail($item->distribution_id);
                    $batch = UmrahBatch::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($distribution->umrah_batch_id);
                    $locked->setRelation('distributionItem', $item);
                    if (! $distribution instanceof Distribution || ! $batch instanceof UmrahBatch) {
                        throw ValidationException::withMessages(['allocation' => 'A valid distribution allocation is required.']);
                    }
                    $this->authorization->authorizeMutation($actor, $batch, ProcurementPermissions::RECEIVE);
                    $this->assertIndividualDistribution($distribution, $batch);
                    $this->assertPilgrimInScope($locked->pilgrim()->withoutGlobalScopes()->firstOrFail(), $batch);

                    return $this->storePilgrimEvidence(
                        $locked,
                        [
                            'file' => $file,
                            'type' => $this->evidenceType($type),
                            'metadata' => $this->evidenceMetadata($type, $metadata),
                        ],
                        $actor,
                    );
                },
                ['pilgrim_distribution_item_id' => $allocationId, 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException || $previous instanceof \RuntimeException) {
                throw $previous;
            }

            throw $exception;
        }
    }

    private function assertIndividualDistribution(Distribution $distribution, UmrahBatch $batch): void
    {
        if (! $distribution->isIndividualMode()) {
            throw ValidationException::withMessages([
                'receipt_mode' => 'Pilgrim receipts require individual receipt mode.',
            ]);
        }
        if ($distribution->isCancelled()) {
            throw ValidationException::withMessages([
                'distribution' => 'A cancelled distribution cannot receive pilgrim receipts.',
            ]);
        }
        if (! $batch->is_active) {
            throw ValidationException::withMessages([
                'umrah_batch_id' => 'An inactive Umrah batch cannot receive pilgrim receipts.',
            ]);
        }
    }

    private function assertPilgrimInScope(Pilgrim $pilgrim, UmrahBatch $batch): void
    {
        if ((int) $pilgrim->umrah_batch_id !== (int) $batch->getKey()) {
            throw ValidationException::withMessages([
                'pilgrim_id' => 'The pilgrim must belong to the distribution batch.',
            ]);
        }
        if ((int) $pilgrim->office_id !== (int) $batch->office_id) {
            throw ValidationException::withMessages([
                'pilgrim_id' => 'The pilgrim must belong to the distribution organizational scope.',
            ]);
        }
        if (! $pilgrim->is_active) {
            throw ValidationException::withMessages([
                'pilgrim_id' => 'An inactive pilgrim cannot receive a distribution.',
            ]);
        }
    }

    private function assertItemAvailability(DistributionItem $item, UmrahBatch $batch): void
    {
        $id = (int) $item->procurement_item_id;
        $received = $this->receivedQuantities([$id], (int) $batch->office_id)[$id] ?? '0.00';
        $distributedElsewhere = $this->distributedQuantitiesForItems(
            [$id],
            (int) $batch->office_id,
            (int) $item->distribution_id,
        )[$id] ?? '0.00';
        $remaining = $this->nonNegative(bcsub($received, $distributedElsewhere, 2));
        if (bccomp((string) $item->quantity, $remaining, 2) > 0) {
            throw ValidationException::withMessages([
                'quantity' => sprintf('Distribution quantity exceeds available received quantity of %s.', $remaining),
            ]);
        }
    }

    private function assertAllocationTotals(
        Collection $existing,
        ?PilgrimDistributionItem $current,
        string $quantity,
        string $status,
        DistributionItem $item,
    ): void {
        $allocated = '0.00';
        $received = '0.00';
        foreach ($existing as $candidate) {
            if ($current instanceof PilgrimDistributionItem && $candidate->is($current)) {
                continue;
            }
            $allocated = bcadd($allocated, (string) $candidate->quantity, 2);
            if ($candidate->countsTowardsReceived()) {
                $received = bcadd($received, (string) $candidate->quantity, 2);
            }
        }

        if (bccomp(bcadd($allocated, $quantity, 2), (string) $item->quantity, 2) > 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Cumulative pilgrim allocations cannot exceed the distribution quantity.',
            ]);
        }
        if ($status === PilgrimDistributionItem::STATUS_RECEIVED
            && bccomp(bcadd($received, $quantity, 2), (string) $item->quantity, 2) > 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Cumulative received pilgrim quantities cannot exceed the distribution quantity.',
            ]);
        }
    }

    private function pilgrimStatus(mixed $status): string
    {
        $status ??= PilgrimDistributionItem::STATUS_PENDING;
        if (! is_string($status) || ! in_array($status, PilgrimDistributionItem::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'The pilgrim distribution status is invalid.']);
        }

        return $status;
    }

    private function pilgrimEvidence(array $data): array
    {
        $raw = $data['evidence'] ?? [];
        if ($raw === [] && array_key_exists('attachments', $data)) {
            $raw = $data['attachments'];
        }
        if ($raw instanceof UploadedFile) {
            $raw = [$raw];
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['evidence' => 'Pilgrim evidence must be uploaded files.']);
        }

        $result = [];
        foreach ($raw as $index => $entry) {
            if ($entry instanceof UploadedFile) {
                $entry = ['file' => $entry, 'type' => 'photo', 'metadata' => []];
            }
            if (! is_array($entry) || ! ($entry['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(["evidence.{$index}.file" => 'Pilgrim evidence must be an uploaded file.']);
            }
            $type = $this->evidenceType((string) ($entry['type'] ?? 'photo'));
            $result[] = [
                'file' => $entry['file'],
                'type' => $type,
                'metadata' => $this->evidenceMetadata($type, is_array($entry['metadata'] ?? null) ? $entry['metadata'] : []),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function evidenceMetadata(string $type, array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || mb_strlen((string) $value) > 255) {
                throw ValidationException::withMessages(['metadata' => 'Pilgrim evidence metadata must contain short scalar values.']);
            }
        }

        return [...$metadata, 'evidence_type' => $type];
    }

    private function evidenceType(string $type): string
    {
        if (! in_array($type, ['photo', 'surat_jalan'], true)) {
            throw ValidationException::withMessages(['evidence_type' => 'Pilgrim evidence must be a photo or surat jalan.']);
        }

        return $type;
    }

    /** @param array{file: UploadedFile, type: string, metadata: array<string, mixed>} $evidence */
    private function storePilgrimEvidence(
        PilgrimDistributionItem $allocation,
        array $evidence,
        User $actor,
    ): Attachment {
        return $this->attachments->store(
            $evidence['file'],
            $allocation,
            $actor,
            'pilgrim-receipt-'.$evidence['type'],
            $evidence['metadata'],
            $evidence['type'] === 'photo'
                ? ['image/jpeg', 'image/png', 'image/webp']
                : ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        );
    }

    /** @return array<int, string> */
    public function availability(UmrahBatch|int|null $batch = null): array
    {
        $officeId = $batch === null ? null : (int) ($batch instanceof UmrahBatch ? $batch->office_id : UmrahBatch::query()->findOrFail($this->positiveInteger($batch, 'umrah_batch_id'))->office_id);
        $ids = ProcurementItem::query()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $this->availableQuantities($ids, $officeId);
    }

    public function availableQuantity(ProcurementItem|int $item, UmrahBatch|int|null $batch = null): string
    {
        $id = $item instanceof ProcurementItem ? (int) $item->getKey() : $this->positiveInteger($item, 'procurement_item_id');
        $officeId = $batch === null ? null : (int) ($batch instanceof UmrahBatch ? $batch->office_id : UmrahBatch::query()->findOrFail($this->positiveInteger($batch, 'umrah_batch_id'))->office_id);

        return $this->availableQuantities([$id], $officeId)[$id] ?? '0.00';
    }

    /** @return array<int, string> */
    public function remainingAvailability(UmrahBatch|int|null $batch = null): array
    {
        return $this->availability($batch);
    }

    /** @return array<int, string> */
    public function batchTotals(UmrahBatch|int $batch): array
    {
        $id = $batch instanceof UmrahBatch ? (int) $batch->getKey() : $this->positiveInteger($batch, 'umrah_batch_id');

        return $this->distributedQuantities($id);
    }

    /** @return array<int, string> */
    public function distributedQuantities(UmrahBatch|int|null $batch = null): array
    {
        $query = DistributionItem::query()
            ->join('distributions', 'distributions.id', '=', 'distribution_items.distribution_id')
            ->whereIn('distributions.status', [Distribution::STATUS_RECORDED, Distribution::STATUS_COMPLETED])
            ->select('distribution_items.procurement_item_id')
            ->selectRaw('SUM(distribution_items.quantity) AS total_quantity')
            ->groupBy('distribution_items.procurement_item_id');
        if ($batch !== null) {
            $id = $batch instanceof UmrahBatch ? (int) $batch->getKey() : $this->positiveInteger($batch, 'umrah_batch_id');
            $query->where('distributions.umrah_batch_id', $id);
        }

        $totals = [];
        foreach ($query->get() as $row) {
            $totals[(int) $row->procurement_item_id] = bcadd((string) $row->total_quantity, '0.00', 2);
        }

        return $totals;
    }

    /** @return array<int, string> */
    public function totals(Distribution|UmrahBatch|int $subject): array
    {
        if ($subject instanceof Distribution) {
            $totals = [];
            foreach ($subject->items as $line) {
                $id = (int) $line->procurement_item_id;
                $totals[$id] = bcadd($totals[$id] ?? '0.00', (string) $line->quantity, 2);
            }

            return $totals;
        }

        return $this->batchTotals($subject);
    }

    /** @param list<int> $ids @return array<int, string> */
    private function availableQuantities(array $ids, ?int $officeId = null): array
    {
        if ($ids === []) {
            return [];
        }
        $received = $this->receivedQuantities($ids, $officeId);
        $distributed = $this->distributedQuantitiesForItems($ids, $officeId);
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->nonNegative(bcsub($received[$id] ?? '0.00', $distributed[$id] ?? '0.00', 2));
        }

        return $result;
    }

    /** @param list<int> $ids @return array<int, string> */
    private function receivedQuantities(array $ids, ?int $officeId = null): array
    {
        $superseded = GoodsReceipt::query()->whereNotNull('correction_of_id')->pluck('correction_of_id')->map(static fn (mixed $id): int => (int) $id)->all();
        $query = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('purchase_order_items', 'purchase_order_items.id', '=', 'goods_receipt_items.purchase_order_item_id')
            ->whereIn('purchase_order_items.procurement_item_id', $ids)
            ->whereNotNull('purchase_order_items.procurement_item_id')
            ->when($officeId !== null, fn ($q) => $q->where('goods_receipts.office_id', $officeId))
            ->when($superseded !== [], fn ($q) => $q->whereNotIn('goods_receipt_items.goods_receipt_id', $superseded))
            ->select(['purchase_order_items.procurement_item_id', 'goods_receipt_items.quantity'])
            ->get();
        $totals = [];
        foreach ($query as $line) {
            $id = (int) $line->procurement_item_id;
            $totals[$id] = bcadd($totals[$id] ?? '0.00', (string) $line->quantity, 2);
        }

        return $totals;
    }

    /** @param list<int> $ids @return array<int, string> */
    private function distributedQuantitiesForItems(array $ids, ?int $officeId = null, ?int $excludeDistributionId = null): array
    {
        $rows = DistributionItem::query()
            ->join('distributions', 'distributions.id', '=', 'distribution_items.distribution_id')
            ->whereIn('distributions.status', [Distribution::STATUS_RECORDED, Distribution::STATUS_COMPLETED])
            ->whereIn('distribution_items.procurement_item_id', $ids)
            ->when($excludeDistributionId !== null, fn ($q) => $q->where('distribution_items.distribution_id', '!=', $excludeDistributionId))
            ->join('umrah_batches', 'umrah_batches.id', '=', 'distributions.umrah_batch_id')
            ->when($officeId !== null, fn ($q) => $q->where('umrah_batches.office_id', $officeId))
            ->select(['distribution_items.procurement_item_id', 'distribution_items.quantity'])
            ->lockForUpdate()
            ->get();
        $totals = [];
        foreach ($rows as $line) {
            $id = (int) $line->procurement_item_id;
            $totals[$id] = bcadd($totals[$id] ?? '0.00', (string) $line->quantity, 2);
        }

        return $totals;
    }

    /** @param list<int> $ids */
    private function lockStockRows(array $ids): void
    {
        ProcurementItem::query()->whereIn('id', $ids)->lockForUpdate()->get();
        $receiptIds = GoodsReceiptItem::query()->join('purchase_order_items', 'purchase_order_items.id', '=', 'goods_receipt_items.purchase_order_item_id')->whereIn('purchase_order_items.procurement_item_id', $ids)->pluck('goods_receipt_items.goods_receipt_id')->all();
        if ($receiptIds !== []) {
            GoodsReceipt::query()->whereIn('id', $receiptIds)->lockForUpdate()->get();
            GoodsReceiptItem::query()->whereIn('goods_receipt_id', $receiptIds)->lockForUpdate()->get();
        }
    }

    /** @return list<array{procurement_item_id:int,quantity:string}> */
    private function validatedLines(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages(['lines' => 'At least one distribution line is required.']);
        }
        $lines = [];
        $seen = [];
        foreach (array_values($raw) as $index => $entry) {
            $id = is_array($entry) ? ($entry['procurement_item_id'] ?? $entry['item_id'] ?? null) : null;
            $id = $this->positiveInteger($id, "lines.{$index}.procurement_item_id");
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Each procurement item may occur only once per distribution.']);
            }
            $seen[$id] = true;
            $lines[] = ['procurement_item_id' => $id, 'quantity' => $this->quantity(is_array($entry) ? ($entry['quantity'] ?? null) : null, "lines.{$index}.quantity")];
        }

        return $lines;
    }

    private function receiptMode(mixed $mode): string
    {
        $mode ??= Distribution::RECEIPT_MODE_BATCH;
        if (! is_string($mode) || ! in_array($mode, Distribution::RECEIPT_MODES, true)) {
            throw ValidationException::withMessages(['receipt_mode' => 'The distribution receipt mode must be batch or individual.']);
        }

        return $mode;
    }

    private function quantity(mixed $value, string $field): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([$field => 'Distribution quantities must be positive decimals with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['distributed_at' => 'A valid distribution date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['distributed_at' => 'A valid distribution date is required.']);
        }
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (! ((is_int($value) && $value > 0) || (is_string($value) && preg_match('/\A[1-9]\d*\z/', $value) === 1))) {
            throw ValidationException::withMessages([$field => 'A valid positive integer is required.']);
        }

        return (int) $value;
    }

    private function nonNegative(string $value): string
    {
        return bccomp($value, '0.00', 2) < 0 ? '0.00' : $value;
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active || ! $actor->is(auth()->user())) {
            throw new AuthorizationException('An active authenticated distribution actor is required.');
        }

        return $actor;
    }
}
