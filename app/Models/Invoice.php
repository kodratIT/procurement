<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PARTIAL = self::STATUS_PARTIALLY_PAID;

    public const STATUS_PAID = 'paid';

    public const MATCH_STATUS_MATCHED = 'matched';

    public const MATCH_STATUS_MISMATCHED = 'mismatched';

    public const REVIEW_STATUS_PENDING = 'pending';

    public const REVIEW_STATUS_APPROVED = 'approved';

    public const REVIEW_STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const PAYMENT_STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_PAID,
    ];

    /** @var list<string> */
    public const MATCH_STATUSES = [
        self::MATCH_STATUS_MATCHED,
        self::MATCH_STATUS_MISMATCHED,
    ];

    /** @var list<string> */
    public const REVIEW_STATUSES = [
        self::REVIEW_STATUS_PENDING,
        self::REVIEW_STATUS_APPROVED,
        self::REVIEW_STATUS_REJECTED,
    ];

    protected $fillable = [
        'purchase_order_id',
        'vendor_id',
        'recorded_by_id',
        'office_id',
        'branch_id',
        'department_id',
        'currency',
        'invoice_number',
        'normalized_invoice_number',
        'total_amount',
        'due_date',
        'status',
        'match_status',
        'review_status',
        'mismatch_reason',
        'matched_at',
        'approved_at',
        'notes',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $invoice): void {
            if (! in_array((string) $invoice->status, self::PAYMENT_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'The invoice payment status is invalid.']);
            }
            if (! in_array((string) $invoice->match_status, self::MATCH_STATUSES, true)) {
                throw ValidationException::withMessages(['match_status' => 'The invoice match status is invalid.']);
            }
            if (! in_array((string) $invoice->review_status, self::REVIEW_STATUSES, true)) {
                throw ValidationException::withMessages(['review_status' => 'The invoice review status is invalid.']);
            }
            if ($invoice->exists && $invoice->isDirty([
                'purchase_order_id',
                'vendor_id',
                'invoice_number',
                'normalized_invoice_number',
                'total_amount',
                'due_date',
                'currency',
            ])) {
                throw new LogicException('Invoice identity and financial terms are immutable.');
            }
            if ($invoice->exists && $invoice->isDirty('status')) {
                throw new LogicException('Invoice payment status is derived from recorded payments.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Invoice history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'vendor_id' => 'integer',
            'recorded_by_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'total_amount' => 'decimal:2',
            'due_date' => 'date',
            'matched_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNPAID);
    }

    public function paymentTotal(): string
    {
        return bcadd((string) $this->payments()->sum('amount'), '0.00', 2);
    }

    public function outstandingAmount(): string
    {
        $outstanding = bcsub((string) $this->total_amount, $this->paymentTotal(), 2);

        return bccomp($outstanding, '0.00', 2) > 0 ? $outstanding : '0.00';
    }

    public function paymentStatus(): string
    {
        $paid = $this->paymentTotal();
        $total = (string) $this->total_amount;

        if (bccomp($paid, '0.00', 2) <= 0) {
            return self::STATUS_UNPAID;
        }

        return bccomp($paid, $total, 2) >= 0
            ? self::STATUS_PAID
            : self::STATUS_PARTIALLY_PAID;
    }

    public function syncPaymentStatus(): string
    {
        $status = $this->paymentStatus();

        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        $this->setRawAttributes(array_merge($this->getRawOriginal(), ['status' => $status]), true);

        return $status;
    }

    public function isOverdue(): bool
    {
        return $this->paymentStatus() !== self::STATUS_PAID && $this->due_date?->isPast() === true;
    }
}
