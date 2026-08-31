<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    public const TYPE_GOODS = 'goods';

    public const TYPE_SERVICES = 'services';

    public const TYPE_GOODS_AND_SERVICES = 'goods_and_services';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_GOODS => 'Barang',
        self::TYPE_SERVICES => 'Jasa',
        self::TYPE_GOODS_AND_SERVICES => 'Barang dan jasa',
    ];

    protected $fillable = [
        'code',
        'name',
        'vendor_type',
        'contact_name',
        'phone',
        'email',
        'address',
        'tax_number',
        'is_active',
    ];

    protected $attributes = [
        'vendor_type' => self::TYPE_GOODS,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailableForNewTransactions(Builder $query): Builder
    {
        return $query->active();
    }

    /** @return HasMany<VendorItem> */
    public function items(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
