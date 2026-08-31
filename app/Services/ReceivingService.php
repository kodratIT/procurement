<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class ReceivingService
{
    /** @var list<string> */
    private const PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const DELIVERY_NOTE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(PurchaseOrder $order, array $data, ?User $actor = null): GoodsReceipt
    {
        $actor = $this->activeActor($actor);
        $this->authorization->authorizeMutation($actor, $order, ProcurementPermissions::RECEIVE);

        return $this->transaction->run(
            'record purchase order receipt',
            function () use ($order, $data, $actor): GoodsReceipt {
                $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());
                $this->assertReceivable($lockedOrder);
                $lines = $this->validatedLines($lockedOrder, $data['lines'] ?? null);
                $receiver = $this->receiver($lockedOrder, $data['receiver_id'] ?? $actor->getKey());

                return $this->createLockedReceipt($lockedOrder, $data, $lines, $receiver, $actor);
            },
            ['purchase_order_id' => $order->getKey(), 'actor_id' => $actor->getKey()],
        );
    }

    /** @param array<string, mixed> $data */
    public function receive(PurchaseOrder $order, array $data, ?User $actor = null): GoodsReceipt
    {
        return $this->record($order, $data, $actor);
    }

    /** @param array<string, mixed> $data */
    public function correct(GoodsReceipt $receipt, array $data, string $reason, ?User $actor = null): GoodsReceipt
    {
        $actor = $this->activeActor($actor);
        $receipt->loadMissing('purchaseOrder');
        $order = $receipt->purchaseOrder;
        if (! $order instanceof PurchaseOrder) {
            throw ValidationException::withMessages(['goods_receipt' => 'The receipt purchase order could not be found.']);
        }
        $this->authorization->authorizeMutation($actor, $order, ProcurementPermissions::CORRECT_RECEIPT);
        $this->assertReason($reason);

        return $this->transaction->run(
            'correct purchase order receipt',
            function () use ($receipt, $data, $reason, $actor): GoodsReceipt {
                $lockedReceipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
                $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($lockedReceipt->purchase_order_id);
                $this->assertReceivable($lockedOrder);
                if (GoodsReceipt::query()->where('correction_of_id', $lockedReceipt->getKey())->exists()) {
                    throw ValidationException::withMessages(['goods_receipt' => 'This receipt has already been corrected.']);
                }

                $lines = $this->validatedLines($lockedOrder, $data['lines'] ?? null, [$lockedReceipt->getKey()]);
                $receiver = $this->receiver($lockedOrder, $data['receiver_id'] ?? $actor->getKey());
                $data['correction_of_id'] = $lockedReceipt->getKey();
                $data['correction_reason'] = trim($reason);

                return $this->createLockedReceipt($lockedOrder, $data, $lines, $receiver, $actor, [$lockedReceipt->getKey()]);
            },
            ['goods_receipt_id' => $receipt->getKey(), 'actor_id' => $actor->getKey()],
        );
    }

    /** @param array<string, mixed> $data */
    public function correctReceipt(GoodsReceipt $receipt, array $data, string $reason, ?User $actor = null): GoodsReceipt
    {
        return $this->correct($receipt, $data, $reason, $actor);
    }

    public function status(PurchaseOrder|int $order): string
    {
        $order = $order instanceof PurchaseOrder ? $order : PurchaseOrder::query()->findOrFail($order);

        return $this->statusForTotals($order, $this->receivedTotals($order));
    }

    public function receiptStatus(PurchaseOrder|int $order): string
    {
        return $this->status($order);
    }

    /** @return array<int, string> */
    public function receivedQuantities(PurchaseOrder|int $order): array
    {
        $order = $order instanceof PurchaseOrder ? $order : PurchaseOrder::query()->findOrFail($order);

        return $this->receivedTotals($order);
    }

    /** @return array<int, string> */
    public function remainingQuantities(PurchaseOrder|int $order): array
    {
        $order = $order instanceof PurchaseOrder ? $order : PurchaseOrder::query()->findOrFail($order);
        $received = $this->receivedTotals($order);

        return $order->items->mapWithKeys(fn (PurchaseOrderItem $item): array => [
            $item->getKey() => bcsub((string) $item->quantity, $received[$item->getKey()] ?? '0.00', 2),
        ])->all();
    }

    /** @param array<string, mixed> $metadata */
    public function attachEvidence(
        GoodsReceipt $receipt,
        UploadedFile $file,
        string $type,
        array $metadata = [],
        ?User $actor = null,
    ): Attachment {
        $actor = $this->activeActor($actor);
        $receipt->loadMissing('purchaseOrder');
        if (! $receipt->purchaseOrder instanceof PurchaseOrder) {
            throw ValidationException::withMessages(['goods_receipt' => 'The receipt purchase order could not be found.']);
        }
        $this->authorization->authorizeMutation($actor, $receipt->purchaseOrder, ProcurementPermissions::RECEIVE);
        $type = $this->validateEvidenceType($type);
        $metadata = $this->validateMetadata($type, $metadata);

        return $this->attachments->store(
            $file,
            $receipt,
            $actor,
            'goods-receipt-'.$type,
            $metadata,
            $type === 'photo' ? self::PHOTO_MIME_TYPES : self::DELIVERY_NOTE_MIME_TYPES,
        );
    }

    /** @param list<array{purchase_order_item_id:int,quantity:string}> $lines */
    private function createLockedReceipt(
        PurchaseOrder $order,
        array $data,
        array $lines,
        User $receiver,
        User $actor,
        array $excludedReceiptIds = [],
    ): GoodsReceipt {
        $status = $this->statusForTotals($order, $this->receivedTotals($order, $excludedReceiptIds), $lines);
        $receipt = GoodsReceipt::create([
            'purchase_order_id' => $order->getKey(),
            'received_date' => $this->date($data['received_date'] ?? null),
            'receiver_id' => $receiver->getKey(),
            'office_id' => $order->office_id,
            'branch_id' => $order->branch_id,
            'department_id' => $order->department_id,
            'status' => $status,
            'correction_of_id' => $data['correction_of_id'] ?? null,
            'correction_reason' => $data['correction_reason'] ?? null,
            'notes' => $this->text($data['notes'] ?? null),
        ]);

        foreach ($lines as $line) {
            $receipt->items()->create($line);
        }

        foreach ($this->evidence($data) as $evidence) {
            $this->attachEvidence($receipt, $evidence['file'], $evidence['type'], $evidence['metadata'], $actor);
        }

        activity('procurement')
            ->performedOn($receipt)
            ->causedBy($actor)
            ->event($receipt->isCorrection() ? 'goods_receipt_corrected' : 'goods_receipt_recorded')
            ->withProperties([
                'purchase_order_id' => $order->getKey(),
                'goods_receipt_id' => $receipt->getKey(),
                'correction_of_id' => $receipt->correction_of_id,
                'status' => $status,
                'lines' => $lines,
            ])
            ->log($receipt->isCorrection() ? 'Goods receipt corrected.' : 'Goods receipt recorded.');

        return $receipt->fresh(['purchaseOrder', 'receiver', 'items.purchaseOrderItem', 'attachments']);
    }

    /** @param list<array<string, mixed>>|null $rawLines @return list<array{purchase_order_item_id:int,quantity:string}> */
    private function validatedLines(PurchaseOrder $order, mixed $rawLines, array $excludedReceiptIds = []): array
    {
        if (! is_array($rawLines) || $rawLines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one purchase order line must be received.']);
        }
        $items = PurchaseOrderItem::query()
            ->where('purchase_order_id', $order->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (PurchaseOrderItem $item): int => (int) $item->getKey());
        $received = $this->receivedTotals($order, $excludedReceiptIds);
        $lines = [];
        $seen = [];

        foreach (array_values($rawLines) as $index => $rawLine) {
            if (! is_array($rawLine) || ! is_numeric($rawLine['purchase_order_item_id'] ?? null)) {
                throw ValidationException::withMessages(["lines.{$index}.purchase_order_item_id" => 'Each receipt line must identify a purchase order item.']);
            }
            $itemId = (int) $rawLine['purchase_order_item_id'];
            if (isset($seen[$itemId]) || ! $items->has($itemId)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'The receipt line is not a unique item on this purchase order.']);
            }
            $quantity = $this->quantity($rawLine['quantity'] ?? null, "lines.{$index}.quantity");
            $remaining = bcsub((string) $items[$itemId]->quantity, $received[$itemId] ?? '0.00', 2);
            if (bccomp($quantity, $remaining, 2) > 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => sprintf('Receipt quantity exceeds the remaining purchase order quantity of %s.', $remaining)]);
            }
            $seen[$itemId] = true;
            $lines[] = ['purchase_order_item_id' => $itemId, 'quantity' => $quantity];
        }

        return $lines;
    }

    /** @return array<int, string> */
    private function receivedTotals(PurchaseOrder $order, array $excludedReceiptIds = []): array
    {
        $superseded = GoodsReceipt::query()->whereNotNull('correction_of_id')->pluck('correction_of_id')->map(static fn (mixed $id): int => (int) $id)->all();
        $excluded = array_values(array_unique([...$superseded, ...$excludedReceiptIds]));
        $query = GoodsReceiptItem::query()->whereHas('goodsReceipt', function ($query) use ($order, $excluded): void {
            $query->where('purchase_order_id', $order->getKey());
            if ($excluded !== []) {
                $query->whereNotIn('id', $excluded);
            }
        });
        $totals = [];
        foreach ($query->get() as $line) {
            $itemId = (int) $line->purchase_order_item_id;
            $totals[$itemId] = bcadd($totals[$itemId] ?? '0.00', (string) $line->quantity, 2);
        }

        return $totals;
    }

    /** @param array<int, string> $received @param list<array{purchase_order_item_id:int,quantity:string}> $additional */
    private function statusForTotals(PurchaseOrder $order, array $received, array $additional = []): string
    {
        $hasReceived = false;
        $complete = true;
        foreach ($order->items as $item) {
            $quantity = bcadd($received[$item->getKey()] ?? '0.00', $this->additionalQuantity($additional, (int) $item->getKey()), 2);
            $hasReceived = $hasReceived || bccomp($quantity, '0.00', 2) > 0;
            $complete = $complete && bccomp($quantity, (string) $item->quantity, 2) >= 0;
        }
        if (! $hasReceived) {
            return GoodsReceipt::STATUS_NOT_RECEIVED;
        }

        return $complete ? GoodsReceipt::STATUS_COMPLETE : GoodsReceipt::STATUS_PARTIALLY_RECEIVED;
    }

    /** @param list<array{purchase_order_item_id:int,quantity:string}> $lines */
    private function additionalQuantity(array $lines, int $itemId): string
    {
        foreach ($lines as $line) {
            if ($line['purchase_order_item_id'] === $itemId) {
                return $line['quantity'];
            }
        }

        return '0.00';
    }

    private function assertReceivable(PurchaseOrder $order): void
    {
        if (! in_array($order->statusValue(), [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages(['purchase_order' => 'Only an approved or issued purchase order can receive goods or services.']);
        }
    }

    private function receiver(PurchaseOrder $order, mixed $receiverId): User
    {
        if (! is_numeric($receiverId)) {
            throw ValidationException::withMessages(['receiver_id' => 'A receiver is required.']);
        }
        $receiver = User::query()->find((int) $receiverId);
        if (! $receiver instanceof User || ! $receiver->is_active || ! $receiver->assignments()->currentlyActive()->where('office_id', $order->office_id)->exists()) {
            throw ValidationException::withMessages(['receiver_id' => 'The receiver must have an active assignment in the purchase order office.']);
        }

        return $receiver;
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['received_date' => 'A valid receipt date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['received_date' => 'A valid receipt date is required.']);
        }
    }

    private function quantity(mixed $value, string $field): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([$field => 'Receipt quantities must be positive decimals with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    /** @return list<array{file:UploadedFile,type:string,metadata:array<string,mixed>}> */
    private function evidence(array $data): array
    {
        $evidence = $data['evidence'] ?? [];
        if ($evidence === [] && isset($data['attachments'])) {
            $evidence = array_map(static fn (mixed $file): array => ['file' => $file, 'type' => 'surat_jalan', 'metadata' => []], (array) $data['attachments']);
        }
        $result = [];
        foreach ((array) $evidence as $index => $entry) {
            if ($entry instanceof UploadedFile) {
                $entry = ['file' => $entry, 'type' => 'surat_jalan', 'metadata' => []];
            }
            if (! is_array($entry) || ! ($entry['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(["evidence.{$index}.file" => 'Receipt evidence must be an uploaded file.']);
            }
            $type = $this->validateEvidenceType((string) ($entry['type'] ?? 'surat_jalan'));
            $result[] = [
                'file' => $entry['file'],
                'type' => $type,
                'metadata' => $this->validateMetadata($type, is_array($entry['metadata'] ?? null) ? $entry['metadata'] : []),
            ];
        }

        return $result;
    }

    private function validateEvidenceType(string $type): string
    {
        if (! in_array($type, ['photo', 'surat_jalan'], true)) {
            throw ValidationException::withMessages(['evidence_type' => 'Receipt evidence must be a photo or surat jalan.']);
        }

        return $type;
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function validateMetadata(string $type, array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || mb_strlen((string) $value) > 255) {
                throw ValidationException::withMessages(['metadata' => 'Receipt evidence metadata must contain short scalar values.']);
            }
        }

        return [...$metadata, 'evidence_type' => $type];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Receipt notes must be text no longer than 10,000 characters.']);
        }

        return $value;
    }

    private function assertReason(string $reason): void
    {
        if (blank($reason) || mb_strlen($reason) > 10000) {
            throw ValidationException::withMessages(['reason' => 'A correction reason is required.']);
        }
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active || ! $actor->is(auth()->user())) {
            throw new AuthorizationException('An active authenticated receiving actor is required.');
        }

        return $actor;
    }
}
