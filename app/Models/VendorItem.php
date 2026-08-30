<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'item_id',
        'price',
        'currency',
        'price_valid_from',
        'price_valid_until',
        'vendor_sku',
        'min_order_qty',
        'lead_time_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_valid_from' => 'date',
            'price_valid_until' => 'date',
            'min_order_qty' => 'integer',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
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
