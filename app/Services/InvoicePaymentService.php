<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainMutationException;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class InvoicePaymentService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
        private readonly AttachmentService $attachments,
        private readonly AccessContextService $context,
        private readonly FeatureModuleService $featureModules,
    ) {}

    /**
     * Record a manual invoice payment and optionally its private proof.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Invoice $invoice, array $data, ?User $actor = null): Payment
    {
        $actor = $this->activeActor($actor);

        try {
            return $this->transaction->run(
                'record invoice payment',
                function () use ($invoice, $data, $actor): Payment {
                    $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
                    $this->authorization->authorizeMutation($actor, $locked, ProcurementPermissions::MANAGE_FINANCE);
                    if ($locked->review_status !== Invoice::REVIEW_STATUS_APPROVED) {
                        throw ValidationException::withMessages(['invoice' => 'Only an approved invoice can receive a payment.']);
                    }

                    $amount = $this->money($data['amount'] ?? null);
                    $paymentDate = $this->date($data['payment_date'] ?? null);
                    $reference = $this->reference($data['reference_number'] ?? null);
                    if (Payment::query()->where('invoice_id', $locked->getKey())->where('reference_number', $reference)->exists()) {
                        throw ValidationException::withMessages(['reference_number' => 'This payment reference already exists for the invoice.']);
                    }

                    $remaining = $locked->outstandingAmount();
                    if (bccomp($amount, $remaining, 2) > 0) {
                        throw ValidationException::withMessages(['amount' => sprintf('Payment exceeds the remaining invoice balance of %s.', $remaining)]);
                    }

                    $before = $this->invoiceSnapshot($locked);
                    $payment = Payment::create([
                        'invoice_id' => $locked->getKey(),
                        'recorded_by_id' => $actor->getKey(),
                        'amount' => $amount,
                        'payment_date' => $paymentDate,
                        'reference_number' => $reference,
                        'notes' => $this->text($data['notes'] ?? null),
                    ]);
                    $attachment = $this->proofFile($data);
                    $attachmentIds = [];
                    if ($attachment instanceof UploadedFile) {
                        $attachmentIds[] = $this->storeProof($payment, $attachment, $this->proofMetadata($data), $actor)->getKey();
                    }

                    $status = $locked->syncPaymentStatus();
                    $after = $this->invoiceSnapshot($locked->fresh());
                    activity('finance')
                        ->performedOn($payment)
                        ->causedBy($actor)
                        ->event('invoice_payment_recorded')
                        ->withProperties([
                            'before' => $before,
                            'after' => $after,
                            'payment_id' => $payment->getKey(),
                            'amount' => $amount,
                            'reference_number' => $reference,
                            'status' => $status,
                            'attachment_ids' => $attachmentIds,
                            'actor_id' => $actor->getKey(),
                            'access_context' => $this->context->snapshot(),
                        ])
                        ->log('Invoice payment recorded.');

                    return $payment->fresh(['invoice', 'recordedBy', 'attachments']);
                },
                ['invoice_id' => $invoice->getKey(), 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException) {
                $this->auditDenied($invoice, $actor, 'record', $previous);
                throw $previous;
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function pay(Invoice $invoice, array $data, ?User $actor = null): Payment
    {
        return $this->record($invoice, $data, $actor);
    }

    /**
     * Attach additional private proof to an immutable payment record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function attachEvidence(Payment $payment, UploadedFile $file, array $metadata = [], ?User $actor = null): Attachment
    {
        $actor = $this->activeActor($actor);
        $invoice = $payment->invoice()->firstOrFail();

        try {
            return $this->transaction->run(
                'attach invoice payment proof',
                function () use ($payment, $file, $metadata, $actor): Attachment {
                    $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
                    $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($lockedPayment->invoice_id);
                    $this->authorization->authorizeMutation($actor, $lockedInvoice, ProcurementPermissions::MANAGE_FINANCE);
                    $attachment = $this->storeProof($lockedPayment, $file, $metadata, $actor);
                    activity('finance')
                        ->performedOn($lockedPayment)
                        ->causedBy($actor)
                        ->event('invoice_payment_proof_attached')
                        ->withProperties([
                            'invoice_id' => $lockedInvoice->getKey(),
                            'payment_id' => $lockedPayment->getKey(),
                            'attachment_id' => $attachment->getKey(),
                            'metadata' => $metadata,
                            'actor_id' => $actor->getKey(),
                            'access_context' => $this->context->snapshot(),
                        ])
                        ->log('Private invoice payment proof attached.');

                    return $attachment->fresh(['attachable', 'uploader']);
                },
                ['payment_id' => $payment->getKey(), 'invoice_id' => $invoice->getKey(), 'actor_id' => $actor->getKey()],
            );
        } catch (DomainMutationException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof AuthorizationException) {
                $this->auditDenied($invoice, $actor, 'attach_proof', $previous, $payment->getKey());
                throw $previous;
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $metadata */
    public function attachProof(Payment $payment, UploadedFile $file, array $metadata = [], ?User $actor = null): Attachment
    {
        return $this->attachEvidence($payment, $file, $metadata, $actor);
    }

    private function storeProof(Payment $payment, UploadedFile $file, array $metadata, User $actor): Attachment
    {
        return $this->attachments->store(
            $file,
            $payment,
            $actor,
            'payment-proof',
            $metadata,
            ['application/pdf', 'image/jpeg', 'image/png'],
        );
    }

    /** @param array<string, mixed> $data */
    private function proofFile(array $data): ?UploadedFile
    {
        foreach (['proof', 'proof_attachment', 'attachment'] as $key) {
            $value = $data[$key] ?? null;
            if ($value instanceof UploadedFile) {
                return $value;
            }
            if (is_array($value) && ($value['file'] ?? null) instanceof UploadedFile) {
                return $value['file'];
            }
        }

        $evidence = $data['evidence'] ?? null;
        if ($evidence instanceof UploadedFile) {
            return $evidence;
        }
        if (is_array($evidence)) {
            foreach ($evidence as $entry) {
                if ($entry instanceof UploadedFile) {
                    return $entry;
                }
                if (is_array($entry) && ($entry['file'] ?? null) instanceof UploadedFile) {
                    return $entry['file'];
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function proofMetadata(array $data): array
    {
        $metadata = $data['proof_metadata'] ?? null;
        if (is_array($metadata)) {
            return $metadata;
        }

        $evidence = $data['evidence'] ?? null;
        if (is_array($evidence)) {
            foreach ($evidence as $entry) {
                if (is_array($entry) && is_array($entry['metadata'] ?? null)) {
                    return $entry['metadata'];
                }
            }
        }

        foreach (['proof', 'proof_attachment', 'attachment'] as $key) {
            $value = $data[$key] ?? null;
            if (is_array($value) && is_array($value['metadata'] ?? null)) {
                return $value['metadata'];
            }
        }

        return [];
    }

    /** @return array{payment_total:string,outstanding:string,status:string} */
    private function invoiceSnapshot(Invoice $invoice): array
    {
        return [
            'payment_total' => $invoice->paymentTotal(),
            'outstanding' => $invoice->outstandingAmount(),
            'status' => $invoice->paymentStatus(),
        ];
    }

    private function activeActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! $actor->is_active || ! $actor->is(auth()->user())) {
            throw new AuthorizationException('An active authenticated finance user is required.');
        }

        $this->featureModules->assertEnabled(FeatureRegistry::FEATURE_INVOICES, $actor);

        return $actor;
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1 || bccomp((string) $value, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amounts must be positive decimals with at most two decimal places.']);
        }

        return bcadd((string) $value, '0.00', 2);
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['payment_date' => 'A valid payment date is required.']);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['payment_date' => 'A valid payment date is required.']);
        }
    }

    private function reference(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 100) {
            throw ValidationException::withMessages(['reference_number' => 'A payment reference is required.']);
        }

        return trim($value);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Payment notes must be text up to 10,000 characters.']);
        }

        return trim($value);
    }

    private function auditDenied(Invoice $invoice, User $actor, string $operation, AuthorizationException $exception, ?int $paymentId = null): void
    {
        activity('finance')
            ->performedOn($invoice)
            ->causedBy($actor)
            ->event('invoice_payment_mutation_denied')
            ->withProperties([
                'operation' => $operation,
                'invoice_id' => $invoice->getKey(),
                'payment_id' => $paymentId,
                'actor_id' => $actor->getKey(),
                'reason' => $exception->getMessage(),
                'access_context' => $this->context->snapshot(),
            ])
            ->log('Invoice payment mutation denied.');
    }
}
