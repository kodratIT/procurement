<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'item_id',
        'reference_price',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reference_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailableForNewTransactions(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereHas('vendor', fn (Builder $vendor): Builder => $vendor->availableForNewTransactions())
            ->whereHas('item', fn (Builder $item): Builder => $item->availableForNewTransactions());
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class, 'item_id');
    }
}
