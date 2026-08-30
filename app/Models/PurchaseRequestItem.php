<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'procurement_item_id',
        'procurement_unit_id',
        'item_name',
        'unit_name',
        'quantity',
        'unit_price',
        'line_total',
        'notes',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ((float) $item->quantity <= 0) {
                throw new \InvalidArgumentException('quantity must be greater than zero.');
            }
            if ((float) $item->unit_price < 0) {
                throw new \InvalidArgumentException('unit_price must not be negative.');
            }

            $item->calculateLineTotal();
        });

        static::saved(function (self $item): void {
            $item->purchaseRequest?->recalculateTotal();
        });

        static::deleted(function (self $item): void {
            $item->purchaseRequest?->recalculateTotal();
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'procurement_item_id' => 'integer',
            'procurement_unit_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function procurementUnit(): BelongsTo
    {
        return $this->belongsTo(ProcurementUnit::class);
    }

    /**
     * Server-side line calculation: line_total = quantity x unit_price.
     * Runs on every save; the stored value never comes from the client.
     */
    public function calculateLineTotal(): void
    {
        $this->line_total = (string) (bcmul(
            (string) ($this->quantity ?? 0),
            (string) ($this->unit_price ?? 0),
            2
        ));
    }
}
