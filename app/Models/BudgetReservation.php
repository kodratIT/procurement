<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BudgetReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BudgetReservation extends Model
{
    /** @use HasFactory<BudgetReservationFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_RELEASED = 'released';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_RELEASED,
        self::STATUS_COMMITTED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    public const AVAILABLE_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_COMMITTED,
    ];

    protected $fillable = [
        'budget_id',
        'purchase_request_id',
        'amount',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_RESERVED,
        'amount' => '0.00',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reservation): void {
            if (! in_array($reservation->status, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The budget reservation status is invalid.',
                ]);
            }

            if ((float) $reservation->amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The budget reservation amount must not be negative.',
                ]);
            }
        });
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereIn('status', self::AVAILABLE_STATUSES);
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, self::AVAILABLE_STATUSES, true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('finance')
            ->logOnly(['budget_id', 'purchase_request_id', 'amount', 'status'])
            ->logOnlyDirty();
    }
}
