<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementVariant extends Model
{
    use HasFactory;

    public const TYPE_UKURAN = 'ukuran';

    public const TYPE_WARNA = 'warna';

    public const TYPE_BAHAN = 'bahan';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_UKURAN,
        self::TYPE_WARNA,
        self::TYPE_BAHAN,
    ];

    protected $fillable = [
        'item_id',
        'variation_type',
        'code',
        'name',
        'value',
        'attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
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
            ->whereHas('item', fn (Builder $item): Builder => $item->availableForNewTransactions());
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class, 'item_id');
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'procurement_variant_id');
    }

    public function deactivate(): bool
    {
        return $this->forceFill(['is_active' => false])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill(['is_active' => true])->save();
    }
}
