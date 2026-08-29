<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementCategory extends Model
{
    use HasFactory;

    public const TYPE_GOODS = 'goods';

    public const TYPE_SERVICE = 'service';

    public const TYPE_MIXED = 'mixed';

    public const TYPES = [
        self::TYPE_GOODS => 'Barang',
        self::TYPE_SERVICE => 'Jasa',
        self::TYPE_MIXED => 'Campuran',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'requires_batch',
        'requires_vendor',
        'receiving',
        'invoice',
        'jamaah',
        'is_active',
    ];

    protected $attributes = [
        'type' => self::TYPE_GOODS,
        'requires_batch' => false,
        'requires_vendor' => false,
        'receiving' => false,
        'invoice' => false,
        'jamaah' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'requires_batch' => 'boolean',
            'requires_vendor' => 'boolean',
            'receiving' => 'boolean',
            'invoice' => 'boolean',
            'jamaah' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'category_id');
    }
}
