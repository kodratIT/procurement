<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeatureFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlag extends Model
{
    /** @use HasFactory<FeatureFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'enabled',
        'updated_by',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'updated_by' => 'integer',
        ];
    }
}
