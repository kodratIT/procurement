<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementVariant extends Model
{
    use HasFactory;

    public const TYPE_UKURAN = 'ukuran';

    public const TYPE_WARNA = 'warna';

    public const TYPE_BAHAN = 'bahan';

    public const TYPES = [self::TYPE_UKURAN, self::TYPE_WARNA, self::TYPE_BAHAN];

    protected $fillable = ['item_id', 'variation_type', 'code', 'name', 'value', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class, 'item_id');
    }
}
