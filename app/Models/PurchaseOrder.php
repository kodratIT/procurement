<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Services\PurchaseRequestTotalCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = PurchaseOrderStatus::Draft->value;

    public const STATUS_PENDING_APPROVAL = PurchaseOrderStatus::PendingApproval->value;

    public const STATUS_APPROVED = PurchaseOrderStatus::Approved->value;

    public const STATUS_ISSUED = PurchaseOrderStatus::Issued->value;

    public const STATUS_REVISION_PENDING_APPROVAL = PurchaseOrderStatus::RevisionPendingApproval->value;

    public const STATUS_REJECTED = PurchaseOrderStatus::Rejected->value;

    public const STATUS_CANCELLED = PurchaseOrderStatus::Cancelled->value;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_ISSUED,
        self::STATUS_REVISION_PENDING_APPROVAL,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    public const MATERIAL_FIELDS = [
        'vendor_id',
        'quotation_id',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'terms',
        'delivery_date',
        'notes',
    ];

    protected $fillable = [
        'po_number',
        'purchase_request_id',
        'vendor_id',
        'quotation_id',
        'created_by_id',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'terms',
        'delivery_date',
        'notes',
        'status',
        'approved_by_id',
        'approved_at',
    ];

    protected $attributes = [
        'currency' => 'IDR',
        'subtotal_amount' => '0.00',
        'discount_amount' => '0.00',
        'tax_amount' => '0.00',
        'shipping_amount' => '0.00',
        'total_amount' => '0.00',
        'status' => self::STATUS_DRAFT,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            if (! in_array((string) $order->getRawOriginal('status'), self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The purchase order status is invalid.',
                ]);
            }

            if ($order->exists && $order->isDirty(['po_number', 'purchase_request_id', 'office_id'])) {
                throw new \LogicException('Purchase order identity and number are immutable.');
            }

            if ($order->exists && $order->getRawOriginal('status') === self::STATUS_APPROVED) {
                if ($order->isDirty(self::MATERIAL_FIELDS)) {
                    throw new \LogicException('Approved purchase order financial changes require a revision and re-approval.');
                }

                if ($order->isDirty('status') && $order->status !== PurchaseOrderStatus::RevisionPendingApproval) {
                    throw new \LogicException('Approved purchase order status changes require the revision service.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'vendor_id' => 'integer',
            'quotation_id' => 'integer',
            'created_by_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'cost_center_id' => 'integer',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'delivery_date' => 'date',
            'approved_by_id' => 'integer',
            'approved_at' => 'datetime',
            'status' => PurchaseOrderStatus::class,
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function approvedQuotation(): BelongsTo
    {
        return $this->quotation();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === PurchaseOrderStatus::Approved;
    }

    public function isReceivable(): bool
    {
        return in_array($this->statusValue(), [self::STATUS_APPROVED, self::STATUS_ISSUED], true);
    }

    public function statusValue(): string
    {
        return $this->status instanceof PurchaseOrderStatus ? $this->status->value : (string) $this->status;
    }

    public function isEditableBeforeApproval(): bool
    {
        $status = $this->status instanceof PurchaseOrderStatus ? $this->status->value : (string) $this->status;

        return in_array($status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL], true);
    }

    public function calculatedSubtotal(): string
    {
        $subtotal = '0.00';
        foreach ($this->items as $item) {
            $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
        }

        return $subtotal;
    }

    public function calculatedTotal(): string
    {
        $total = bcsub($this->calculatedSubtotal(), (string) $this->discount_amount, 2);
        $total = bcadd($total, (string) $this->tax_amount, 2);

        return bcadd($total, (string) $this->shipping_amount, 2);
    }

    public function syncCalculatedTotals(): void
    {
        $subtotal = $this->calculatedSubtotal();
        $total = $this->calculatedTotal();

        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update([
                'subtotal_amount' => $subtotal,
                'total_amount' => $total,
                'updated_at' => now(),
            ]);
        $this->forceFill(['subtotal_amount' => $subtotal, 'total_amount' => $total]);
    }

    public function calculateLineTotal(mixed $quantity, mixed $unitPrice): string
    {
        return app(PurchaseRequestTotalCalculator::class)->lineTotal($quantity, $unitPrice);
    }
}
