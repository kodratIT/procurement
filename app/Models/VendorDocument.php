<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    use HasFactory;

    public const TYPE_NPWP = 'npwp';

    public const TYPE_SIUP = 'siup';

    public const TYPE_NIB = 'nib';

    public const TYPE_AKTA = 'akta';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_NPWP => 'NPWP',
        self::TYPE_SIUP => 'SIUP',
        self::TYPE_NIB => 'NIB',
        self::TYPE_AKTA => 'Akta Perusahaan',
        self::TYPE_OTHER => 'Lainnya',
    ];

    protected $fillable = [
        'vendor_id',
        'name',
        'document_type',
        'file_path',
        'file_name',
        'issued_at',
        'expires_at',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
