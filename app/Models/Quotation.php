<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PurchaseRequestTotalCalculator;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Packstub\Flow\Concerns\HasWorkflows;

class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, HasWorkflows;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED];

    protected $fillable = [
        'purchase_request_id',
        'vendor_id',
        'created_by_id',
        'quotation_number',
        'quoted_at',
        'valid_until',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'status',
        'notes',
        'submitted_at',
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
        static::saving(function (self $quotation): void {
            if (! in_array($quotation->status, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The quotation status is invalid.',
                ]);
            }

            if ($quotation->exists && $quotation->isDirty('purchase_request_id')) {
                throw new \LogicException('A quotation cannot be moved to another purchase request.');
            }

            if ((! $quotation->exists || $quotation->isDirty('vendor_id'))
                && (! is_numeric($quotation->vendor_id)
                    || ! Vendor::query()->availableForNewTransactions()->whereKey((int) $quotation->vendor_id)->exists())) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'The selected vendor is inactive or invalid.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'vendor_id' => 'integer',
            'created_by_id' => 'integer',
            'quoted_at' => 'date',
            'valid_until' => 'date',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(QuotationRecommendation::class);
    }

    public function scopeForPurchaseRequest(Builder $query, int $purchaseRequestId): Builder
    {
        return $query->where('purchase_request_id', $purchaseRequestId);
    }

    public function calculatedSubtotal(): string
    {
        $total = '0.00';
        $calculator = app(PurchaseRequestTotalCalculator::class);

        foreach ($this->items as $item) {
            $total = bcadd($total, $calculator->lineTotal($item->quantity, $item->unit_price), 2);
        }

        return $total;
    }

    public function calculatedTotal(): string
    {
        $subtotal = $this->calculatedSubtotal();
        $total = bcsub($subtotal, $this->money($this->discount_amount), 2);
        $total = bcadd($total, $this->money($this->tax_amount), 2);

        return bcadd($total, $this->money($this->shipping_amount), 2);
    }

    public function syncCalculatedTotals(): void
    {
        $subtotal = $this->calculatedSubtotal();
        $total = bcsub($subtotal, $this->money($this->discount_amount), 2);
        $total = bcadd($total, $this->money($this->tax_amount), 2);
        $total = bcadd($total, $this->money($this->shipping_amount), 2);

        DB::table($this->getTable())->where($this->getKeyName(), $this->getKey())->update([
            'subtotal_amount' => $subtotal,
            'total_amount' => $total,
            'updated_at' => now(),
        ]);
        $this->forceFill(['subtotal_amount' => $subtotal, 'total_amount' => $total]);
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value) || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) !== 1) {
            throw new \InvalidArgumentException('Quotation monetary values must be non-negative decimals.');
        }

        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
