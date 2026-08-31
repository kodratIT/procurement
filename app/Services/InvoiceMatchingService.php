<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainMutationException;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class InvoiceMatchingService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
        private readonly ReceivingService $receiving,
    ) {}

    /**
     * Evaluate an invoice against the approved PO and received quantities.
     *
     * @param  array<string, mixed>  $data
     * @return array{matched:bool,reasons:list<string>,po_total:string,received_total:string,remaining_po_total:string,remaining_received_total:string,invoice_total:string,lines:list<array<string,mixed>>}
     */
    public function check(PurchaseOrder $order, array $data): array
    {
        $order->loadMissing(['items', 'goodsReceipts.items']);
        $total = $this->money($data['total_amount'] ?? null, 'total_amount');
        $lines = $this->normaliseLines($order, $data['lines'] ?? [], false);
        $receivedQuantities = $this->receiving->receivedQuantities($order);
        $receivedTotal = $this->receivedTotal($order->items, $receivedQuantities);
        $previouslyInvoiced = $this->previouslyInvoiced($order);
        $poTotal = $this->money($order->total_amount, 'purchase_order');
        $remainingPoTotal = $this->nonNegative(bcsub($poTotal, $previouslyInvoiced, 2));
        $remainingReceivedTotal = $this->nonNegative(bcsub($receivedTotal, $previouslyInvoiced, 2));
        $reasons = [];

        if (bccomp($total, $remainingPoTotal, 2) > 0) {
            $reasons[] = sprintf('Invoice total %s exceeds the remaining approved PO amount of %s.', $total, $remainingPoTotal);
        }
        if (bccomp($total, $remainingReceivedTotal, 2) > 0) {
            $reasons[] = sprintf('Invoice total %s exceeds the remaining received evidence amount of %s.', $total, $remainingReceivedTotal);
        }

        $lineResult = $this->checkLines($order, $lines, $receivedQuantities);
        $reasons = [...$reasons, ...$lineResult['reasons']];
        if ($lines !== [] && bccomp($lineResult['total'], $total, 2) !== 0) {
            $reasons[] = sprintf('Invoice lines total %s does not equal the invoice total of %s.', $lineResult['total'], $total);
        }

        return [
            'matched' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'po_total' => $poTotal,
            'received_total' => $receivedTotal,
            'remaining_po_total' => $remainingPoTotal,
            'remaining_received_total' => $remainingReceivedTotal,
            'invoice_total' => $total,
            'lines' => $lineResult['lines'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function match(PurchaseOrder $order, array $data): array
    {
        return $this->check($order, $data);
    }

    /**
     * Record a matched invoice and its private evidence.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(PurchaseOrder $order, array $data, ?User $actor = null): Invoice
    {
        $actor = $this->activeActor($actor);

        try {
            return $this->transaction->run(
                'record purchase order invoice',
                function () use ($order, $data, $actor): Invoice {
                    $lockedOrder = PurchaseOrder::query()
                        ->with(['items', 'goodsReceipts.items'])
                        ->lockForUpdate()
                        ->findOrFail($order->getKey());
                    $this->authorization->authorizeMutation($actor, $lockedOrder, ProcurementPermissions::MANAGE_FINANCE);
                    $this->assertInvoiceOrder($lockedOrder);
                    $number = $this->invoiceNumber($data['invoice_number'] ?? null);
                    $normalizedNumber = $this->normaliseInvoiceNumber($number);
                    if (Invoice::query()->where('vendor_id', $lockedOrder->vendor_id)->where('normalized_invoice_number', $normalizedNumber)->exists()) {
                        throw ValidationException::withMessages(['invoice_number' => 'This invoice number already exists for the vendor.']);
                    }
                    $dueDate = $this->date($data['due_date'] ?? null);
                    $total = $this->money($data['total_amount'] ?? null, 'total_amount');
                    $match = $this->check($lockedOrder, ['total_amount' => $total, 'lines' => $data['lines'] ?? []]);
                    $this->assertMatched($match);
                    $invoice = Invoice::create([
                        'purchase_order_id' => $lockedOrder->getKey(),
                        'vendor_id' => $lockedOrder->vendor_id,
                        'recorded_by_id' => $actor->getKey(),
                        'office_id' => $lockedOrder->office_id,
                        'branch_id' => $lockedOrder->branch_id,
                        'department_id' => $lockedOrder->department_id,
                        'currency' => $lockedOrder->currency,
                        'invoice_number' => $number,
                        'normalized_invoice_number' => $normalizedNumber,
                        'total_amount' => $total,
                        'due_date' => $dueDate,
                        'status' => Invoice::STATUS_UNPAID,
                        'match_status' => Invoice::MATCH_STATUS_MATCHED,
                        'review_status' => Invoice::REVIEW_STATUS_PENDING,
                        'matched_at' => now(),
                        'notes' => $this->text($data['notes'] ?? null),
                    ]);
                    foreach ($match['lines'] as $line) {
                        $invoice->items()->create($line);
                    }
                    $attachmentIds = [];
                    foreach ($this->evidence($data) as $evidence) {
                        $attachmentIds[] = $this->attachEvidence($invoice, $evidence['file'], $evidence['metadata'], $actor)->getKey();
                    }
                    activity('finance')
                        ->performedOn($invoice)
                        ->causedBy($actor)
                        ->event('invoice_recorded')
                        ->withProperties([
                            'purchase_order_id' => $lockedOrder->getKey(),
                            'invoice_number' => $number,
                            'match' => $match,
                            'attachment_ids' => $attachmentIds,
                        ])
                        ->log('Invoice recorded and matched to purchase order evidence.');

                    return $invoice->fresh(['purchaseOrder', 'vendor', 'items.purchaseOrderItem', 'payments', 'attachments']);
                },
                ['purchase_order_id' => $order->getKey(), 'actor_id' => $actor->getKey()],
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
    public function create(PurchaseOrder $order, array $data, ?User $actor = null): Invoice
    {
        return $this->record($order, $data, $actor);
    }

    public function approve(Invoice $invoice, ?User $actor = null): Invoice
    {
        $actor = $this->activeActor($actor);

        try {
            return $this->transaction->run(
                'approve purchase order invoice',
                function () use ($invoice, $actor): Invoice {
                    $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
                    $this->authorization->authorizeMutation($actor, $locked, ProcurementPermissions::MANAGE_FINANCE);
                    if ($locked->match_status !== Invoice::MATCH_STATUS_MATCHED) {
                        throw ValidationException::withMessages(['invoice' => $locked->mismatch_reason ?: 'Invoice matching failed.']);
                    }
                    if ($locked->review_status === Invoice::REVIEW_STATUS_APPROVED) {
                        return $locked->fresh(['purchaseOrder', 'items', 'payments', 'attachments']);
                    }
                    $locked->forceFill([
                        'review_status' => Invoice::REVIEW_STATUS_APPROVED,
                        'approved_at' => now(),
                    ])->save();
                    activity('finance')
                        ->performedOn($locked)
                        ->causedBy($actor)
                        ->event('invoice_approved')
                        ->withProperties(['purchase_order_id' => $locked->purchase_order_id])
                        ->log('Invoice approved after purchase order matching.');

                    return $locked->fresh(['purchaseOrder', 'items', 'payments', 'attachments']);
                },
                ['invoice_id' => $invoice->getKey(), 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException) {
                throw $previous;
            }

            throw $exception;
        }
    }

    public function review(Invoice $invoice, ?User $actor = null): Invoice
    {
        return $this->approve($invoice, $actor);
    }

    /** @param array<string, mixed> $metadata */
    public function attachEvidence(Invoice $invoice, UploadedFile $file, array $metadata = [], ?User $actor = null): Attachment
    {
        $actor = $this->activeActor($actor);
        $invoice->loadMissing('purchaseOrder');
        if (! $invoice->purchaseOrder instanceof PurchaseOrder) {
            throw ValidationException::withMessages(['invoice' => 'The invoice purchase order could not be found.']);
        }
        $this->authorization->authorizeMutation($actor, $invoice, ProcurementPermissions::MANAGE_FINANCE);
        $attachment = $this->attachments->store($file, $invoice, $actor, 'invoice', $metadata, ['application/pdf', 'image/jpeg', 'image/png']);
        activity('finance')
            ->performedOn($invoice)
            ->causedBy($actor)
            ->event('invoice_evidence_attached')
            ->withProperties(['attachment_id' => $attachment->getKey(), 'metadata' => $metadata])
            ->log('Private invoice evidence attached.');

        return $attachment;
    }

    /** @return array<string, mixed> */
    public function explanation(Invoice $invoice): array
    {
        return [
            'match_status' => $invoice->match_status,
            'review_status' => $invoice->review_status,
            'reason' => $invoice->mismatch_reason,
            'purchase_order_id' => $invoice->purchase_order_id,
            'invoice_total' => (string) $invoice->total_amount,
        ];
    }

    /** @param array<string, mixed> $match */
    private function assertMatched(array $match): void
    {
        if ($match['matched'] === true) {
            return;
        }

        throw ValidationException::withMessages([
            'matching' => implode(' ', $match['reasons']),
            'mismatch_reason' => implode(' ', $match['reasons']),
        ]);
    }

    private function assertInvoiceOrder(PurchaseOrder $order): void
    {
        if (! in_array($order->statusValue(), [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages(['purchase_order' => 'Only an approved or issued purchase order can receive invoices.']);
        }
    }

    private function previouslyInvoiced(PurchaseOrder $order): string
    {
        return bcadd((string) Invoice::query()
            ->where('purchase_order_id', $order->getKey())
            ->where('match_status', Invoice::MATCH_STATUS_MATCHED)
            ->sum('total_amount'), '0.00', 2);
    }

    /** @param Collection<int, PurchaseOrderItem> $items @param array<int, string> $received */
    private function receivedTotal(Collection $items, array $received): string
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, bcmul((string) ($received[$item->getKey()] ?? '0.00'), (string) $item->unit_price, 2), 2);
        }

        return $total;
    }

    /** @param array<int, mixed> $rawLines @return list<array<string, mixed>> */
    private function normaliseLines(PurchaseOrder $order, mixed $rawLines, bool $required): array
    {
        if (! is_array($rawLines)) {
            throw ValidationException::withMessages(['lines' => 'Invoice lines must be an array.']);
        }
        if ($required && $rawLines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one invoice line is required.']);
        }

        $items = $order->items->keyBy(fn (PurchaseOrderItem $item): int => (int) $item->getKey());
        $lines = [];
        $seen = [];
        foreach (array_values($rawLines) as $index => $rawLine) {
            $itemId = is_array($rawLine) ? $rawLine['purchase_order_item_id'] ?? null : null;
            if (! ((is_int($itemId) && $itemId > 0) || (is_string($itemId) && preg_match('/\A[1-9]\d*\z/', $itemId) === 1))) {
                throw ValidationException::withMessages(["lines.{$index}.purchase_order_item_id" => 'Each invoice line must identify a purchase order item.']);
            }
            $itemId = (int) $itemId;
            if (isset($seen[$itemId]) || ! $items->has($itemId)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'The invoice line is not a unique item on this purchase order.']);
            }
            $quantity = $this->quantity($rawLine['quantity'] ?? null, "lines.{$index}.quantity");
            $unitPrice = array_key_exists('unit_price', $rawLine) ? $this->money($rawLine['unit_price'], "lines.{$index}.unit_price") : (string) $items[$itemId]->unit_price;
            $lines[] = [
                'purchase_order_item_id' => $itemId,
                'description' => $this->text($rawLine['description'] ?? $items[$itemId]->item_name),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($quantity, $unitPrice, 2),
                'sort_order' => count($lines),
            ];
            $seen[$itemId] = true;
        }

        return $lines;
    }

    /** @param list<array<string,mixed>> $lines @param array<int,string> $received @return array{total:string,reasons:list<string>,lines:list<array<string,mixed>>} */
    private function checkLines(PurchaseOrder $order, array $lines, array $received): array
    {
        $invoiceQuantities = InvoiceItem::query()
            ->whereHas('invoice', fn ($query) => $query->where('purchase_order_id', $order->getKey())->where('match_status', Invoice::MATCH_STATUS_MATCHED))
            ->get()
            ->groupBy('purchase_order_item_id')
            ->map(fn (Collection $items): string => $items->reduce(fn (string $total, InvoiceItem $item): string => bcadd($total, (string) $item->quantity, 2), '0.00'))
            ->all();
        $total = '0.00';
        $reasons = [];
        foreach ($lines as $line) {
            $itemId = (int) $line['purchase_order_item_id'];
            $remaining = $this->nonNegative(bcsub((string) ($received[$itemId] ?? '0.00'), (string) ($invoiceQuantities[$itemId] ?? '0.00'), 2));
            if (bccomp((string) $line['quantity'], $remaining, 2) > 0) {
                $reasons[] = sprintf('Invoice quantity for PO line %d exceeds the remaining received quantity of %s.', $itemId, $remaining);
            }
            $total = bcadd($total, (string) $line['line_total'], 2);
        }

        return ['total' => $total, 'reasons' => $reasons, 'lines' => $lines];
    }

    /** @return list<array{file:UploadedFile,metadata:array<string,mixed>}> */
    private function evidence(array $data): array
    {
        $rawEvidence = $data['evidence'] ?? $data['attachments'] ?? [];
        if (! is_array($rawEvidence) || $rawEvidence === []) {
            throw ValidationException::withMessages(['evidence' => 'At least one invoice evidence attachment is required.']);
        }
        $result = [];
        foreach ($rawEvidence as $index => $entry) {
            if ($entry instanceof UploadedFile) {
                $entry = ['file' => $entry, 'metadata' => []];
            }
            if (! is_array($entry) || ! ($entry['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(["evidence.{$index}.file" => 'Invoice evidence must be an uploaded file.']);
            }
            $result[] = ['file' => $entry['file'], 'metadata' => is_array($entry['metadata'] ?? []) ? $entry['metadata'] : []];
        }

        return $result;
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated finance user is required.');
        }

        return $actor;
    }

    private function invoiceNumber(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 100) {
            throw ValidationException::withMessages(['invoice_number' => 'A valid invoice number is required.']);
        }

        return trim($value);
    }

    private function normaliseInvoiceNumber(string $number): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($number)) ?? trim($number));
    }

    private function money(mixed $value, string $field): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([$field => $field.' must be a positive decimal with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function quantity(mixed $value, string $field): string
    {
        return $this->money($value, $field);
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['due_date' => 'A valid invoice due date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_date' => 'A valid invoice due date is required.']);
        }
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Invoice notes must be text up to 10,000 characters.']);
        }

        return trim($value);
    }

    private function nonNegative(string $value): string
    {
        return bccomp($value, '0.00', 2) < 0 ? '0.00' : $value;
    }
}
