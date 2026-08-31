<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DistributionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class DistributionItem extends Model
{
    /** @use HasFactory<DistributionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'distribution_id',
        'procurement_item_id',
        'quantity',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $item): void {
            if (! $item->distribution_id) {
                throw ValidationException::withMessages(['distribution_id' => 'A distribution is required.']);
            }

            if (! $item->procurement_item_id) {
                throw ValidationException::withMessages(['procurement_item_id' => 'A procurement item is required.']);
            }

            if (! is_numeric($item->quantity) || bccomp((string) $item->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Distribution quantities must be greater than zero.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'distribution_id' => 'integer',
            'procurement_item_id' => 'integer',
            'quantity' => 'decimal:2',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->procurementItem();
    }

    public function pilgrimAllocations(): HasMany
    {
        return $this->hasMany(PilgrimDistributionItem::class);
    }
}
