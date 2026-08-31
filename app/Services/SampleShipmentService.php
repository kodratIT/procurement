<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleShipmentCondition;
use App\Enums\SampleShipmentStatus;
use App\Exceptions\DomainMutationException;
use App\Models\Attachment;
use App\Models\Office;
use App\Models\ProcurementVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SampleShipment;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class SampleShipmentService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        SampleShipment::STATUS_DRAFT => [SampleShipment::STATUS_SUBMITTED],
        SampleShipment::STATUS_SUBMITTED => [SampleShipment::STATUS_PROCUREMENT_REVIEW],
        SampleShipment::STATUS_PROCUREMENT_REVIEW => [SampleShipment::STATUS_APPROVED],
        SampleShipment::STATUS_APPROVED => [SampleShipment::STATUS_SHIPPED],
        SampleShipment::STATUS_SHIPPED => [SampleShipment::STATUS_RECEIVED],
        SampleShipment::STATUS_RECEIVED => [SampleShipment::STATUS_CONFIRMED],
        SampleShipment::STATUS_CONFIRMED => [SampleShipment::STATUS_RETURNED, SampleShipment::STATUS_STORED, SampleShipment::STATUS_COMPLETE],
        SampleShipment::STATUS_RETURNED => [SampleShipment::STATUS_STORED, SampleShipment::STATUS_COMPLETE],
        SampleShipment::STATUS_STORED => [SampleShipment::STATUS_COMPLETE],
        SampleShipment::STATUS_COMPLETE => [],
    ];

    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): SampleShipment
    {
        $actor = $this->activeActor($actor);
        $purchaseOrderId = $this->positiveInteger($data['purchase_order_id'] ?? null, 'purchase_order_id');

        try {
            return $this->transaction->run(
                'create sample shipment',
                function () use ($data, $actor, $purchaseOrderId): SampleShipment {
                    $order = PurchaseOrder::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($purchaseOrderId);
                    if (! $order->isReceivable()) {
                        throw ValidationException::withMessages([
                            'purchase_order_id' => 'Only an approved or issued purchase order can be used for a sample shipment.',
                        ]);
                    }

                    $senderOfficeId = $this->positiveInteger(
                        $data['sender_office_id'] ?? $data['office_id'] ?? $order->office_id,
                        'sender_office_id',
                    );
                    $receiverOfficeId = $this->positiveInteger($data['receiver_office_id'] ?? null, 'receiver_office_id');
                    if ($senderOfficeId === $receiverOfficeId) {
                        throw ValidationException::withMessages([
                            'receiver_office_id' => 'A sample shipment must have a different receiving office.',
                        ]);
                    }
                    if ((int) $order->office_id !== $senderOfficeId) {
                        throw ValidationException::withMessages([
                            'sender_office_id' => 'The sender office must match the purchase order office.',
                        ]);
                    }
                    $this->activeOffice($senderOfficeId, 'sender_office_id');
                    $this->activeOffice($receiverOfficeId, 'receiver_office_id');
                    $this->authorization->authorizeMutation(
                        $actor,
                        ['office_id' => $senderOfficeId],
                        ProcurementPermissions::CREATE,
                    );

                    $senderId = $this->positiveInteger($data['sender_id'] ?? $actor->getKey(), 'sender_id');
                    $this->assertUserInOffice($senderId, $senderOfficeId, 'sender_id');
                    $receiverId = $data['receiver_id'] ?? null;
                    if ($receiverId !== null) {
                        $receiverId = $this->positiveInteger($receiverId, 'receiver_id');
                        $this->assertUserInOffice($receiverId, $receiverOfficeId, 'receiver_id');
                    }
                    $lines = $this->validatedLines($order, $data['lines'] ?? $data['items'] ?? null);
                    $route = $this->approvalRoute($data['approval_route'] ?? null);

                    $shipment = SampleShipment::create([
                        'purchase_order_id' => $order->getKey(),
                        'office_id' => $senderOfficeId,
                        'sender_office_id' => $senderOfficeId,
                        'receiver_office_id' => $receiverOfficeId,
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'cost_center_id' => $data['cost_center_id'] ?? $order->cost_center_id,
                        'purpose' => $this->text($data['purpose'] ?? null, 'purpose'),
                        'requested_at' => $this->date($data['requested_at'] ?? now(), 'requested_at'),
                        'planned_ship_date' => $this->nullableDate($data['planned_ship_date'] ?? null, 'planned_ship_date'),
                        'tracking_no' => $this->nullableText($data['tracking_no'] ?? null, 'tracking_no'),
                        'shipping_cost' => $this->money($data['shipping_cost'] ?? 0),
                        'currency' => $this->currency($data['currency'] ?? 'IDR'),
                        'approval_route' => $route,
                        'condition' => $this->condition($data['condition'] ?? SampleShipmentCondition::Good->value, 'condition'),
                        'ownership' => 'sender_office',
                        'status' => SampleShipment::STATUS_DRAFT,
                        'notes' => $this->nullableText($data['notes'] ?? null, 'notes'),
                    ]);

                    foreach ($lines as $line) {
                        $shipment->items()->create($line);
                    }
                    foreach ($this->evidence($data) as $evidence) {
                        $this->storeEvidence($shipment, $evidence, $actor);
                    }

                    activity('procurement')
                        ->performedOn($shipment)
                        ->causedBy($actor)
                        ->event('sample_shipment_created')
                        ->withProperties([
                            'purchase_order_id' => $order->getKey(),
                            'sender_office_id' => $senderOfficeId,
                            'receiver_office_id' => $receiverOfficeId,
                            'lines' => $lines,
                            'approval_route' => $route,
                        ])
                        ->log('Sample shipment created.');

                    return $shipment->fresh([
                        'purchaseOrder.purchaseRequest',
                        'senderOffice',
                        'receiverOffice',
                        'sender',
                        'receiver',
                        'costCenter',
                        'items.procurementItem',
                        'items.procurementVariant',
                        'attachments',
                    ]);
                },
                ['purchase_order_id' => $purchaseOrderId, 'actor_id' => $actor->getKey()],
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
    public function createShipment(array $data, ?User $actor = null): SampleShipment
    {
        return $this->create($data, $actor);
    }

    /**
     * Change a shipment status through the permitted lifecycle graph.
     *
     * @param  array<string, mixed>|User  $data
     */
    public function transition(SampleShipment|int $shipment, string|SampleShipmentStatus $target, array|User $data = [], ?User $actor = null): SampleShipment
    {
        if ($data instanceof User) {
            $actor = $data;
            $data = [];
        }
        $actor = $this->activeActor($actor);
        $shipmentId = $shipment instanceof SampleShipment ? (int) $shipment->getKey() : $this->positiveInteger($shipment, 'shipment_id');
        $targetValue = $target instanceof SampleShipmentStatus ? $target->value : $target;
        if (! in_array($targetValue, SampleShipmentStatus::values(), true)) {
            throw ValidationException::withMessages(['status' => 'The sample shipment status is invalid.']);
        }

        try {
            return $this->transaction->run(
                'transition sample shipment',
                function () use ($shipmentId, $targetValue, $data, $actor): SampleShipment {
                    $locked = SampleShipment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($shipmentId);
                    $from = $locked->statusValue();
                    if (! in_array($targetValue, self::TRANSITIONS[$from] ?? [], true)) {
                        throw ValidationException::withMessages([
                            'status' => sprintf('A sample shipment cannot move from %s to %s.', $from, $targetValue),
                        ]);
                    }
                    if ($targetValue === SampleShipment::STATUS_CONFIRMED && ! ($data['confirmation'] ?? false)) {
                        throw ValidationException::withMessages([
                            'status' => 'Delivery confirmation must be recorded by the receipt action.',
                        ]);
                    }

                    $receivingSide = in_array($targetValue, [
                        SampleShipment::STATUS_RECEIVED,
                        SampleShipment::STATUS_CONFIRMED,
                        SampleShipment::STATUS_RETURNED,
                        SampleShipment::STATUS_STORED,
                        SampleShipment::STATUS_COMPLETE,
                    ], true);
                    $permission = match ($targetValue) {
                        SampleShipment::STATUS_SUBMITTED => ProcurementPermissions::SUBMIT,
                        SampleShipment::STATUS_PROCUREMENT_REVIEW, SampleShipment::STATUS_APPROVED => ProcurementPermissions::APPROVE,
                        default => $receivingSide ? ProcurementPermissions::RECEIVE : ProcurementPermissions::UPDATE,
                    };
                    $subject = $receivingSide
                        ? ['office_id' => $locked->receiver_office_id]
                        : $locked;
                    $this->authorization->authorizeMutation($actor, $subject, $permission);
                    if ($targetValue === SampleShipment::STATUS_APPROVED
                        && $locked->approval_route === SampleShipment::APPROVAL_ROUTE_FINANCE) {
                        $this->authorization->authorizeMutation(
                            $actor,
                            ['office_id' => $locked->office_id],
                            ProcurementPermissions::MANAGE_FINANCE,
                        );
                    }

                    $updates = ['status' => $targetValue];
                    if ($targetValue === SampleShipment::STATUS_SHIPPED) {
                        $updates['shipped_at'] = $this->date($data['shipped_at'] ?? now(), 'shipped_at');
                    }
                    if ($targetValue === SampleShipment::STATUS_RECEIVED) {
                        $updates['received_at'] = $this->date($data['received_at'] ?? now(), 'received_at');
                    }
                    if ($targetValue === SampleShipment::STATUS_RETURNED) {
                        $updates['returned_at'] = $this->date($data['returned_at'] ?? now(), 'returned_at');
                        $updates['ownership'] = 'returned';
                    }
                    if ($targetValue === SampleShipment::STATUS_STORED) {
                        $updates['ownership'] = 'stored';
                    }
                    if ($targetValue === SampleShipment::STATUS_COMPLETE) {
                        $updates['completed_at'] = $this->date($data['completed_at'] ?? now(), 'completed_at');
                    }

                    $locked->forceFill($updates)->saveQuietly();
                    activity('procurement')
                        ->performedOn($locked)
                        ->causedBy($actor)
                        ->event('sample_shipment_status_changed')
                        ->withProperties(['from' => $from, 'to' => $targetValue])
                        ->log(sprintf('Sample shipment moved from %s to %s.', $from, $targetValue));

                    return $locked->fresh([
                        'purchaseOrder.purchaseRequest',
                        'senderOffice',
                        'receiverOffice',
                        'sender',
                        'receiver',
                        'items.procurementItem',
                        'receipt.attachments',
                        'attachments',
                    ]);
                },
                ['sample_shipment_id' => $shipmentId, 'target' => $targetValue, 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException) {
                throw $previous;
            }
            throw $exception;
        }
    }

    public function submit(SampleShipment|int $shipment, ?User $actor = null): SampleShipment
    {
        return $this->transition($shipment, SampleShipmentStatus::Submitted, [], $actor);
    }

    public function review(SampleShipment|int $shipment, ?User $actor = null): SampleShipment
    {
        return $this->transition($shipment, SampleShipmentStatus::ProcurementReview, [], $actor);
    }

    public function approve(SampleShipment|int $shipment, ?User $actor = null): SampleShipment
    {
        return $this->transition($shipment, SampleShipmentStatus::Approved, [], $actor);
    }

    public function ship(SampleShipment|int $shipment, array $data = [], ?User $actor = null): SampleShipment
    {
        return $this->transition($shipment, SampleShipmentStatus::Shipped, $data, $actor);
    }

    /** @param array<string, mixed> $metadata */
    public function attachEvidence(SampleShipment $shipment, UploadedFile $file, string $type, array $metadata = [], ?User $actor = null): Attachment
    {
        $actor = $this->activeActor($actor);
        $this->authorization->authorizeMutation($actor, $shipment, ProcurementPermissions::UPDATE);
        $type = $this->evidenceType($type);

        return $this->storeEvidence($shipment, [
            'file' => $file,
            'type' => $type,
            'metadata' => $metadata,
        ], $actor);
    }

    /** @param array{file: UploadedFile, type: string, metadata: array<string, mixed>} $evidence */
    private function storeEvidence(SampleShipment $shipment, array $evidence, User $actor): Attachment
    {
        return $this->attachments->store(
            $evidence['file'],
            $shipment,
            $actor,
            'sample-shipment-'.$evidence['type'],
            $evidence['metadata'],
            $evidence['type'] === 'photo'
                ? ['image/jpeg', 'image/png', 'image/webp']
                : ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        );
    }

    /** @param array<string, mixed> $data @return list<array<string, mixed>> */
    private function evidence(array $data): array
    {
        $raw = $data['evidence'] ?? $data['attachments'] ?? [];
        if ($raw instanceof UploadedFile) {
            $raw = [$raw];
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['evidence' => 'Shipment evidence must be uploaded files.']);
        }

        $result = [];
        foreach ($raw as $index => $entry) {
            if ($entry instanceof UploadedFile) {
                $entry = ['file' => $entry, 'type' => 'photo', 'metadata' => []];
            }
            if (! is_array($entry) || ! ($entry['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(["evidence.{$index}.file" => 'Shipment evidence must be an uploaded file.']);
            }
            $result[] = [
                'file' => $entry['file'],
                'type' => $this->evidenceType((string) ($entry['type'] ?? 'photo')),
                'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function validatedLines(PurchaseOrder $order, mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages(['lines' => 'At least one sample shipment item is required.']);
        }
        $orderItems = PurchaseOrderItem::query()->where('purchase_order_id', $order->getKey())->get();
        $byId = $orderItems->keyBy(fn (PurchaseOrderItem $item): int => (int) $item->getKey());
        $byItem = $orderItems->filter(fn (PurchaseOrderItem $item): bool => $item->procurement_item_id !== null)
            ->groupBy('procurement_item_id');
        $seen = [];
        $lines = [];
        foreach (array_values($raw) as $index => $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Each sample shipment item must be an object.']);
            }
            $sourceId = $entry['purchase_order_item_id'] ?? null;
            $source = $sourceId === null
                ? $byItem->get((int) ($entry['procurement_item_id'] ?? $entry['item_id'] ?? 0))?->first()
                : $byId->get($this->positiveInteger($sourceId, "lines.{$index}.purchase_order_item_id"));
            if (! $source instanceof PurchaseOrderItem) {
                throw ValidationException::withMessages(["lines.{$index}" => 'The sample item must belong to the origin purchase order.']);
            }
            $itemId = $this->positiveInteger($entry['procurement_item_id'] ?? $source->procurement_item_id, "lines.{$index}.procurement_item_id");
            if ((int) $source->procurement_item_id !== $itemId) {
                throw ValidationException::withMessages(["lines.{$index}.procurement_item_id" => 'The sample item does not match the origin purchase order line.']);
            }
            $variantId = $entry['procurement_variant_id'] ?? $entry['variant_id'] ?? null;
            $key = $itemId.'-'.($variantId === null ? 'none' : (string) $variantId);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Each item and variant may occur only once per shipment.']);
            }
            $variantId = $variantId === null ? null : $this->positiveInteger($variantId, "lines.{$index}.procurement_variant_id");
            if ($variantId !== null && ! ProcurementVariant::query()->whereKey($variantId)->where('item_id', $itemId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["lines.{$index}.procurement_variant_id" => 'The variant must be active and belong to the selected procurement item.']);
            }
            $quantity = $this->quantity($entry['quantity'] ?? null, "lines.{$index}.quantity");
            if (bccomp($quantity, (string) $source->quantity, 2) > 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => 'Sample quantity cannot exceed the origin purchase order quantity.']);
            }
            $seen[$key] = true;
            $lines[] = [
                'purchase_order_item_id' => $source->getKey(),
                'procurement_item_id' => $itemId,
                'procurement_variant_id' => $variantId,
                'quantity' => $quantity,
                'condition' => $this->condition($entry['condition'] ?? SampleShipmentCondition::Good->value, "lines.{$index}.condition"),
                'ownership' => 'sender_office',
                'notes' => $this->nullableText($entry['notes'] ?? null, "lines.{$index}.notes"),
            ];
        }

        return $lines;
    }

    private function approvalRoute(mixed $route): string
    {
        $route ??= config('procurement.sample_shipments.approval_route', SampleShipment::APPROVAL_ROUTE_PROCUREMENT);
        if (! is_string($route) || ! in_array($route, SampleShipment::APPROVAL_ROUTES, true)) {
            throw ValidationException::withMessages(['approval_route' => 'The sample shipment approval route is invalid.']);
        }

        return $route;
    }

    private function condition(mixed $value, string $field): string
    {
        $value = $value instanceof SampleShipmentCondition ? $value->value : $value;
        if (! is_string($value) || ! in_array($value, SampleShipmentCondition::values(), true)) {
            throw ValidationException::withMessages([$field => 'The sample shipment condition is invalid.']);
        }

        return $value;
    }

    private function evidenceType(string $type): string
    {
        if (! in_array($type, ['photo', 'document'], true)) {
            throw ValidationException::withMessages(['evidence_type' => 'Shipment evidence must be a photo or document.']);
        }

        return $type;
    }

    private function quantity(mixed $value, string $field): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([$field => 'Sample quantities must be positive decimals with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1) {
            throw ValidationException::withMessages(['shipping_cost' => 'Shipping cost must be a non-negative decimal with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function currency(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A[A-Z]{3}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['currency' => 'Currency must be a three-letter uppercase code.']);
        }

        return $value;
    }

    private function text(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$field => 'This field is required.']);
        }
        if (mb_strlen($value) > 255) {
            throw ValidationException::withMessages([$field => 'This field is too long.']);
        }

        return trim($value);
    }

    private function nullableText(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 255) {
            throw ValidationException::withMessages([$field => 'This field is too long.']);
        }

        return trim($value);
    }

    private function date(mixed $value, string $field): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'A valid date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'A valid date is required.']);
        }
    }

    private function nullableDate(mixed $value, string $field): ?string
    {
        return $value === null || $value === '' ? null : $this->date($value, $field);
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (! ((is_int($value) && $value > 0) || (is_string($value) && preg_match('/\A[1-9]\d*\z/', $value) === 1))) {
            throw ValidationException::withMessages([$field => 'A valid positive integer is required.']);
        }

        return (int) $value;
    }

    private function activeOffice(int $officeId, string $field): Office
    {
        $office = Office::query()->withoutGlobalScopes()->whereKey($officeId)->where('is_active', true)->first();
        if (! $office instanceof Office) {
            throw ValidationException::withMessages([$field => 'The selected office is inactive or unavailable.']);
        }

        return $office;
    }

    private function assertUserInOffice(int $userId, int $officeId, string $field): void
    {
        if (! User::query()->whereKey($userId)->where('is_active', true)->whereHas('assignments', fn ($query) => $query->where('office_id', $officeId)->currentlyActive())->exists()) {
            throw ValidationException::withMessages([$field => 'The responsible user must have an active assignment in the selected office.']);
        }
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active || ! $actor->is(auth()->user())) {
            throw new AuthorizationException('An active authenticated sample shipment actor is required.');
        }

        return $actor;
    }
}
