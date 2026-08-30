<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BLACKLISTED = 'blacklisted';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_SUSPENDED => 'Ditangguhkan',
        self::STATUS_BLACKLISTED => 'Blacklist',
        self::STATUS_INACTIVE => 'Nonaktif',
    ];

    protected static function booted(): void
    {
        static::created(function (Vendor $vendor): void {
            $vendor->statusHistory()->create([
                'status' => $vendor->status,
                'note' => $vendor->status_note,
                'changed_by' => auth()->id(),
                'changed_at' => $vendor->status_changed_at ?? now(),
            ]);
        });

        static::updated(function (Vendor $vendor): void {
            if (! $vendor->wasChanged('status')) {
                return;
            }

            $vendor->forceFill(['status_changed_at' => now()])->saveQuietly();
            $vendor->statusHistory()->create([
                'status' => $vendor->status,
                'note' => $vendor->status_note,
                'changed_by' => auth()->id(),
                'changed_at' => $vendor->status_changed_at,
            ]);
        });
    }

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'is_active',
        'status',
        'status_note',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_changed_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(VendorBankAccount::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(VendorTax::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(VendorStatusHistory::class)->latest('changed_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }
}
