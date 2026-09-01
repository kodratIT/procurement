<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PurchaseRequestTotalCalculator;
use Database\Factories\QuotationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class QuotationItem extends Model
{
    /** @use HasFactory<QuotationItemFactory> */
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'purchase_request_item_id',
        'description',
        'specifications',
        'quantity',
        'unit_name',
        'unit_price',
        'line_total',
        'total_price',
        'notes',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $quotation = Quotation::query()->withoutGlobalScopes()->find($item->quotation_id);
            $requestItem = PurchaseRequestItem::query()->find($item->purchase_request_item_id);

            if ($quotation === null || $requestItem === null || (int) $quotation->purchase_request_id !== (int) $requestItem->purchase_request_id) {
                throw ValidationException::withMessages([
                    'purchase_request_item_id' => 'The quotation line must map to an item on its purchase request.',
                ]);
            }

            $duplicate = self::query()
                ->where('quotation_id', $item->quotation_id)
                ->where('purchase_request_item_id', $item->purchase_request_item_id)
                ->when($item->exists, fn ($query) => $query->where('id', '!=', $item->getKey()))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'purchase_request_item_id' => 'A quotation can contain only one line for each purchase request item.',
                ]);
            }

            $quantity = $item->quantity;
            if ($quantity === null || $quantity === '') {
                $quantity = $requestItem->quantity;
            }

            try {
                $lineTotal = app(PurchaseRequestTotalCalculator::class)->lineTotal(
                    $quantity,
                    $item->unit_price,
                );
                $item->quantity = $quantity;
                $item->line_total = $lineTotal;
                $item->total_price = $lineTotal;
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'items' => $exception->getMessage(),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quotation_id' => 'integer',
            'purchase_request_item_id' => 'integer',
            'specifications' => 'array',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'total_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }
}
