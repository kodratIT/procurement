<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementUnit extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'symbol', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'unit_id');
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'procurement_unit_id');
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
