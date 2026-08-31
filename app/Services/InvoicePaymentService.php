<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainMutationException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\DomainTransaction;
use App\Support\ProcurementPermissions;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class InvoicePaymentService
{
    public function __construct(
        private readonly DomainTransaction $transaction,
        private readonly MultiOfficeAuthorization $authorization,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(Invoice $invoice, array $data, ?User $actor = null): Payment
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated finance user is required.');
        }

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
                    $remaining = bcsub((string) $locked->total_amount, $locked->paymentTotal(), 2);
                    if (bccomp($amount, $remaining, 2) > 0) {
                        throw ValidationException::withMessages(['amount' => sprintf('Payment exceeds the remaining invoice balance of %s.', $remaining)]);
                    }
                    $payment = Payment::create([
                        'invoice_id' => $locked->getKey(),
                        'recorded_by_id' => $actor->getKey(),
                        'amount' => $amount,
                        'payment_date' => $this->date($data['payment_date'] ?? null),
                        'reference_number' => $this->reference($data['reference_number'] ?? null),
                        'notes' => $this->text($data['notes'] ?? null),
                    ]);
                    $locked->syncPaymentStatus();
                    activity('finance')
                        ->performedOn($locked)
                        ->causedBy($actor)
                        ->event('invoice_payment_recorded')
                        ->withProperties([
                            'payment_id' => $payment->getKey(),
                            'amount' => $amount,
                            'status' => $locked->paymentStatus(),
                        ])
                        ->log('Invoice payment recorded.');

                    return $payment->fresh(['invoice', 'recordedBy']);
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

    /** @param array<string, mixed> $data */
    public function pay(Invoice $invoice, array $data, ?User $actor = null): Payment
    {
        return $this->record($invoice, $data, $actor);
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
}
