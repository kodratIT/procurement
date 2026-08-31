<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

final class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'quantity',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $item): void {
            if (! is_numeric($item->quantity) || bccomp((string) $item->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'The goods receipt quantity must be greater than zero.']);
            }
            if ($item->exists) {
                throw new LogicException('Goods receipt lines are immutable; use the correction service.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Goods receipt history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'goods_receipt_id' => 'integer',
            'purchase_order_item_id' => 'integer',
            'quantity' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
