<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

final class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'purchase_order_item_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'sort_order',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $item): void {
            if (! is_numeric($item->quantity) || bccomp((string) $item->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Invoice quantities must be positive.']);
            }
            if (! is_numeric($item->unit_price) || bccomp((string) $item->unit_price, '0.00', 2) < 0) {
                throw ValidationException::withMessages(['unit_price' => 'Invoice unit prices cannot be negative.']);
            }
            if ($item->exists && $item->isDirty(['invoice_id', 'purchase_order_item_id', 'quantity', 'unit_price', 'line_total'])) {
                throw new LogicException('Invoice lines are immutable.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Invoice lines are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'purchase_order_item_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
