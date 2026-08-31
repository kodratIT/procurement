<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DistributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

final class Distribution extends Model
{
    /** @use HasFactory<DistributionFactory> */
    use HasFactory;

    public const RECEIPT_MODE_BATCH = 'batch';

    public const RECEIPT_MODE_INDIVIDUAL = 'individual';

    /** @var list<string> */
    public const RECEIPT_MODES = [
        self::RECEIPT_MODE_BATCH,
        self::RECEIPT_MODE_INDIVIDUAL,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECORDED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'umrah_batch_id',
        'distributed_at',
        'receipt_mode',
        'status',
    ];

    protected $attributes = [
        'receipt_mode' => self::RECEIPT_MODE_BATCH,
        'status' => self::STATUS_RECORDED,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $distribution): void {
            if (! in_array((string) $distribution->receipt_mode, self::RECEIPT_MODES, true)) {
                throw ValidationException::withMessages(['receipt_mode' => 'The distribution receipt mode is invalid.']);
            }

            if (! in_array((string) $distribution->status, self::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'The distribution status is invalid.']);
            }

            if (! $distribution->umrah_batch_id) {
                throw ValidationException::withMessages(['umrah_batch_id' => 'A valid Umrah batch is required.']);
            }

            if (! $distribution->distributed_at) {
                throw ValidationException::withMessages(['distributed_at' => 'A distribution date is required.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'umrah_batch_id' => 'integer',
            'distributed_at' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(UmrahBatch::class, 'umrah_batch_id');
    }

    public function umrahBatch(): BelongsTo
    {
        return $this->batch();
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributionItem::class);
    }

    public function pilgrimAllocations(): HasManyThrough
    {
        return $this->hasManyThrough(
            PilgrimDistributionItem::class,
            DistributionItem::class,
            'distribution_id',
            'distribution_item_id',
            'id',
            'id',
        )->select('pilgrim_distribution_items.*');
    }

    public function isIndividualMode(): bool
    {
        return $this->receipt_mode === self::RECEIPT_MODE_INDIVIDUAL;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function countsTowardsAvailability(): bool
    {
        return in_array($this->status, [self::STATUS_RECORDED, self::STATUS_COMPLETED], true);
    }
}
