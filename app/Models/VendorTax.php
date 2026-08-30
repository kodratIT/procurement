<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'tax_number',
        'tax_type',
        'tax_name',
        'address',
        'registered_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
