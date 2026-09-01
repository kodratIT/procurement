<?php

namespace App\Models;

use App\Services\PurchaseRequestTotalCalculator;
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
        'procurement_variant_id',
        'item_name',
        'description',
        'unit_name',
        'variant_name',
        'variant_value',
        'specifications',
        'quantity',
        'unit_price',
        'line_total',
        'notes',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->line_total = app(PurchaseRequestTotalCalculator::class)->lineTotal(
                $item->quantity,
                $item->unit_price,
            );

            $item->validateCatalogReferences();
        });

        static::saved(function (self $item): void {
            if ($item->purchaseRequest !== null) {
                app(PurchaseRequestTotalCalculator::class)->recalculateHeader($item->purchaseRequest);
            }
        });

        static::deleted(function (self $item): void {
            if ($item->purchaseRequest !== null) {
                app(PurchaseRequestTotalCalculator::class)->recalculateHeader($item->purchaseRequest);
            }
        });
    }

    private function validateCatalogReferences(): void
    {
        if ($this->procurement_item_id === null) {
            if ($this->procurement_variant_id !== null || $this->procurement_unit_id !== null) {
                throw new \InvalidArgumentException('Catalog references require an item.');
            }

            return;
        }

        if ($this->exists && ! $this->isDirty([
            'procurement_item_id',
            'procurement_unit_id',
            'procurement_variant_id',
        ])) {
            return;
        }

        $catalogItem = ProcurementItem::query()
            ->availableForNewTransactions()
            ->find($this->procurement_item_id);

        if ($catalogItem === null) {
            throw new \InvalidArgumentException('The item is inactive or unavailable for new transactions.');
        }

        if ($this->procurement_unit_id !== null) {
            if ((int) $this->procurement_unit_id !== (int) $catalogItem->unit_id) {
                throw new \InvalidArgumentException('The unit does not belong to the selected item.');
            }

            $this->unit_name ??= $catalogItem->unit->name;
        }

        $this->item_name ??= $catalogItem->name;

        if ($this->procurement_variant_id === null) {
            return;
        }

        $variant = ProcurementVariant::query()
            ->availableForNewTransactions()
            ->where('item_id', $catalogItem->id)
            ->find($this->procurement_variant_id);

        if ($variant === null) {
            throw new \InvalidArgumentException('The variant is inactive or does not belong to the selected item.');
        }

        $this->variant_name ??= $variant->name;
        $this->variant_value ??= $variant->value;
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'procurement_item_id' => 'integer',
            'procurement_unit_id' => 'integer',
            'procurement_variant_id' => 'integer',
            'specifications' => 'array',
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

    public function procurementVariant(): BelongsTo
    {
        return $this->belongsTo(ProcurementVariant::class);
    }

    /**
     * Server-side line calculation: line_total = quantity x unit_price.
     */
    public function calculateLineTotal(): void
    {
        $this->line_total = app(PurchaseRequestTotalCalculator::class)->lineTotal(
            $this->quantity,
            $this->unit_price,
        );
    }
}
