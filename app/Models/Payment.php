<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'recorded_by_id',
        'amount',
        'payment_date',
        'reference_number',
        'notes',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $payment): void {
            if (! is_numeric($payment->amount) || bccomp((string) $payment->amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amounts must be positive.']);
            }
            if ($payment->exists) {
                throw new LogicException('Payment history is immutable.');
            }
        });

        self::created(function (self $payment): void {
            $payment->invoice?->syncPaymentStatus();
        });

        self::updating(function (): never {
            throw new LogicException('Payment history is immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Payment history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'recorded_by_id' => 'integer',
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
