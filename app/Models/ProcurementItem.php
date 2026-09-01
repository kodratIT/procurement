<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'unit_id',
        'code',
        'name',
        'description',
        'reference_price',
        'reference_currency',
        'specifications',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reference_price' => 'decimal:2',
            'specifications' => 'array',
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
            ->whereHas('category', fn (Builder $category): Builder => $category->active())
            ->whereHas('unit', fn (Builder $unit): Builder => $unit->active());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProcurementUnit::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProcurementVariant::class, 'item_id');
    }

    public function deactivate(): bool
    {
        return $this->forceFill(['is_active' => false])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill(['is_active' => true])->save();
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'procurement_item_id');
    }

    /** @return HasMany<VendorItem> */
    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class, 'item_id');
    }

    /** @return array<string, string> */
    public function specificationList(): array
    {
        return is_array($this->specifications) ? $this->specifications : [];
    }

    public function formattedReferencePrice(): string
    {
        if ($this->reference_price === null) {
            return '-';
        }

        return number_format((float) $this->reference_price, 0, ',', '.').' '.$this->reference_currency;
    }
}
