<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleShipmentCondition;
use App\Exceptions\DomainMutationException;
use App\Models\Attachment;
use App\Models\SampleShipment;
use App\Models\SampleShipmentItem;
use App\Models\SampleShipmentReceipt;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class SampleShipmentReceiptService
{
    /** @var list<string> */
    private const PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const SIGNATURE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
        private readonly FeatureModuleService $featureModules,
    ) {}

    /** @param array<string, mixed> $data */
    public function confirm(SampleShipment|int $shipment, array $data, ?User $actor = null): SampleShipmentReceipt
    {
        $actor = $this->activeActor($actor);
        $shipmentId = $shipment instanceof SampleShipment ? (int) $shipment->getKey() : $this->positiveInteger($shipment, 'shipment_id');

        try {
            return $this->transaction->run(
                'confirm sample shipment receipt',
                function () use ($shipmentId, $data, $actor): SampleShipmentReceipt {
                    $locked = SampleShipment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($shipmentId);
                    if (! in_array($locked->statusValue(), [SampleShipment::STATUS_SHIPPED, SampleShipment::STATUS_RECEIVED], true)) {
                        throw ValidationException::withMessages([
                            'shipment' => 'Only shipped sample shipments can be confirmed as received.',
                        ]);
                    }
                    $receiverOfficeId = (int) $locked->receiver_office_id;
                    $this->authorization->authorizeMutation(
                        $actor,
                        ['office_id' => $receiverOfficeId],
                        ProcurementPermissions::RECEIVE,
                    );
                    $receiverId = $this->positiveInteger($data['receiver_id'] ?? $actor->getKey(), 'receiver_id');
                    if (! User::query()->whereKey($receiverId)->where('is_active', true)->whereHas('assignments', fn ($query) => $query->where('office_id', $receiverOfficeId)->currentlyActive())->exists()) {
                        throw ValidationException::withMessages([
                            'receiver_id' => 'The receiver must have an active assignment in the receiving office.',
                        ]);
                    }

                    $quantities = $this->validatedQuantities($locked, $data);
                    $totalQuantity = array_reduce($quantities, static fn (string $total, string $quantity): string => bcadd($total, $quantity, 2), '0.00');
                    $this->assertRequiredFields($data);
                    $condition = $this->condition($data['condition']);
                    $receivedAt = $this->date($data['received_at']);
                    $disposition = $this->disposition($data['disposition'] ?? SampleShipmentReceipt::DISPOSITION_STORED);
                    $evidence = $this->evidence($data);
                    $this->assertRequiredEvidence($evidence);

                    $receipt = SampleShipmentReceipt::create([
                        'shipment_id' => $locked->getKey(),
                        'receiver_id' => $receiverId,
                        'received_at' => $receivedAt,
                        'quantity' => $totalQuantity,
                        'quantities' => $quantities,
                        'condition' => $condition,
                        'disposition' => $disposition,
                        'ownership' => $this->ownershipForDisposition($disposition),
                        'notes' => $this->nullableText($data['notes'] ?? null),
                    ]);

                    foreach ($evidence as $entry) {
                        $this->storeEvidence($receipt, $entry, $actor);
                    }

                    $updates = [
                        'receiver_id' => $receiverId,
                        'received_at' => $receivedAt,
                        'confirmed_at' => $receivedAt,
                        'condition' => $condition,
                        'ownership' => $this->ownershipForDisposition($disposition),
                        'status' => SampleShipment::STATUS_CONFIRMED,
                    ];
                    $locked->forceFill($updates)->saveQuietly();

                    activity('procurement')
                        ->performedOn($receipt)
                        ->causedBy($actor)
                        ->event('sample_shipment_received')
                        ->withProperties([
                            'shipment_id' => $locked->getKey(),
                            'quantity' => $totalQuantity,
                            'condition' => $condition,
                            'disposition' => $disposition,
                        ])
                        ->log('Sample shipment receipt confirmed.');

                    return $receipt->fresh(['shipment', 'receiver', 'attachments']);
                },
                ['sample_shipment_id' => $shipmentId, 'actor_id' => $actor->getKey()],
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
    public function receive(SampleShipment|int $shipment, array $data, ?User $actor = null): SampleShipmentReceipt
    {
        return $this->confirm($shipment, $data, $actor);
    }

    /** @param array<string, mixed> $metadata */
    public function attachEvidence(SampleShipmentReceipt $receipt, UploadedFile $file, string $type, array $metadata = [], ?User $actor = null): Attachment
    {
        $actor = $this->activeActor($actor);
        $receipt->loadMissing('shipment');
        if (! $receipt->shipment instanceof SampleShipment) {
            throw ValidationException::withMessages(['receipt' => 'The shipment receipt could not be found.']);
        }
        $this->authorization->authorizeMutation($actor, ['office_id' => $receipt->shipment->receiver_office_id], ProcurementPermissions::RECEIVE);
        $type = $this->evidenceType($type);

        return $this->storeEvidence($receipt, ['file' => $file, 'type' => $type, 'metadata' => $metadata], $actor);
    }

    /** @return array<string, string> */
    private function validatedQuantities(SampleShipment $shipment, array $data): array
    {
        $shipment->loadMissing('items');
        $items = $shipment->items->keyBy(fn (SampleShipmentItem $item): int => (int) $item->getKey());
        $rawLines = $data['lines'] ?? $data['quantities'] ?? null;
        if (is_array($rawLines)) {
            $quantities = [];
            foreach ($rawLines as $index => $line) {
                if (is_string($line) && is_numeric($line)) {
                    $quantities[(string) $index] = $this->quantity($line, "quantities.{$index}");

                    continue;
                }
                if (! is_array($line)) {
                    throw ValidationException::withMessages(["lines.{$index}" => 'Each receipt quantity line is invalid.']);
                }
                $itemId = $line['sample_shipment_item_id'] ?? $line['item_id'] ?? null;
                $itemId = $this->positiveInteger($itemId, "lines.{$index}.sample_shipment_item_id");
                if (! $items->has($itemId)) {
                    throw ValidationException::withMessages(["lines.{$index}" => 'The receipt item does not belong to this shipment.']);
                }
                $quantities[(string) $itemId] = $this->quantity($line['quantity'] ?? null, "lines.{$index}.quantity");
            }
            if ($quantities !== []) {
                $this->assertWithinShipmentQuantity($shipment, $quantities);

                return $quantities;
            }
        }

        $quantity = $this->quantity($data['quantity'] ?? null, 'quantity');
        $this->assertWithinShipmentQuantity($shipment, ['total' => $quantity]);

        return ['total' => $quantity];
    }

    /** @param array<string, string> $quantities */
    private function assertWithinShipmentQuantity(SampleShipment $shipment, array $quantities): void
    {
        $total = array_reduce($quantities, static fn (string $sum, string $quantity): string => bcadd($sum, $quantity, 2), '0.00');
        $shipmentTotal = $shipment->items->reduce(static fn (string $sum, SampleShipmentItem $item): string => bcadd($sum, (string) $item->quantity, 2), '0.00');
        if (bccomp($total, $shipmentTotal, 2) > 0) {
            throw ValidationException::withMessages([
                'quantity' => sprintf('Received quantity cannot exceed the shipped quantity of %s.', $shipmentTotal),
            ]);
        }
    }

    /** @param array<string, mixed> $data @return list<array{file: UploadedFile, type: string, metadata: array<string, mixed>}> */
    private function evidence(array $data): array
    {
        $raw = $data['evidence'] ?? $data['attachments'] ?? [];
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['evidence' => 'Receipt evidence must include photo and signature files.']);
        }
        $result = [];
        foreach ($raw as $key => $entry) {
            if ($entry instanceof UploadedFile) {
                $entry = ['file' => $entry, 'type' => is_string($key) ? $key : 'photo', 'metadata' => []];
            }
            if (! is_array($entry) || ! ($entry['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(["evidence.{$key}.file" => 'Receipt evidence must be an uploaded file.']);
            }
            $result[] = [
                'file' => $entry['file'],
                'type' => $this->evidenceType((string) ($entry['type'] ?? 'photo')),
                'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function assertRequiredFields(array $data): void
    {
        $errors = [];
        if (! array_key_exists('condition', $data) || $data['condition'] === null || $data['condition'] === '') {
            $errors['condition'] = 'A valid receipt condition is required.';
        }
        if (! array_key_exists('received_at', $data) || $data['received_at'] === null || $data['received_at'] === '') {
            $errors['received_at'] = 'A valid receipt date is required.';
        }
        if ((bool) config('procurement.sample_shipments.require_receipt_evidence', true)
            && empty($data['evidence'] ?? $data['attachments'] ?? [])) {
            $errors['evidence'] = 'Receipt confirmation requires both photo and signature evidence.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param list<array{file: UploadedFile, type: string, metadata: array<string, mixed>}> $evidence */
    private function assertRequiredEvidence(array $evidence): void
    {
        if (! (bool) config('procurement.sample_shipments.require_receipt_evidence', true)) {
            return;
        }
        $types = array_unique(array_column($evidence, 'type'));
        foreach (['photo', 'signature'] as $required) {
            if (! in_array($required, $types, true)) {
                throw ValidationException::withMessages([
                    'evidence' => 'Receipt confirmation requires both photo and signature evidence.',
                ]);
            }
        }
    }

    /** @param array{file: UploadedFile, type: string, metadata: array<string, mixed>} $evidence */
    private function storeEvidence(SampleShipmentReceipt $receipt, array $evidence, User $actor): Attachment
    {
        return $this->attachments->store(
            $evidence['file'],
            $receipt,
            $actor,
            'sample-shipment-receipt-'.$evidence['type'],
            $evidence['metadata'],
            $evidence['type'] === 'photo' ? self::PHOTO_MIME_TYPES : self::SIGNATURE_MIME_TYPES,
        );
    }

    private function evidenceType(string $type): string
    {
        if (! in_array($type, ['photo', 'signature'], true)) {
            throw ValidationException::withMessages(['evidence_type' => 'Receipt evidence must be a photo or signature.']);
        }

        return $type;
    }

    private function condition(mixed $value): string
    {
        $value = $value instanceof SampleShipmentCondition ? $value->value : $value;
        if (! is_string($value) || ! in_array($value, SampleShipmentCondition::values(), true)) {
            throw ValidationException::withMessages(['condition' => 'A valid receipt condition is required.']);
        }

        return $value;
    }

    private function disposition(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, SampleShipmentReceipt::DISPOSITIONS, true)) {
            throw ValidationException::withMessages(['disposition' => 'A valid receipt disposition is required.']);
        }

        return $value;
    }

    private function ownershipForDisposition(string $disposition): string
    {
        return match ($disposition) {
            SampleShipmentReceipt::DISPOSITION_RETURNED => 'returned',
            SampleShipmentReceipt::DISPOSITION_DAMAGED => 'damaged',
            SampleShipmentReceipt::DISPOSITION_LOST => 'lost',
            default => 'stored',
        };
    }

    private function quantity(mixed $value, string $field): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([$field => 'Received quantities must be positive decimals with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['received_at' => 'A valid receipt date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['received_at' => 'A valid receipt date is required.']);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 255) {
            throw ValidationException::withMessages(['notes' => 'Receipt notes are too long.']);
        }

        return trim($value);
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (! ((is_int($value) && $value > 0) || (is_string($value) && preg_match('/\A[1-9]\d*\z/', $value) === 1))) {
            throw ValidationException::withMessages([$field => 'A valid positive integer is required.']);
        }

        return (int) $value;
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active || ! $actor->is(auth()->user())) {
            throw new AuthorizationException('An active authenticated shipment receipt actor is required.');
        }

        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_SAMPLE_SHIPMENTS, $actor);

        return $actor;
    }
}
