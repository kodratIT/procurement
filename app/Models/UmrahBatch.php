<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ContextScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class UmrahBatch extends Model
{
    use ContextScoped, HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_DEPARTED = 'departed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PLANNED => 'Direncanakan',
        self::STATUS_OPEN => 'Dibuka',
        self::STATUS_CLOSED => 'Ditutup',
        self::STATUS_DEPARTED => 'Berangkat',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'office_id',
        'code',
        'name',
        'departure_date',
        'return_date',
        'capacity',
        'pilgrim_count',
        'status',
        'is_active',
        'disabled_at',
    ];

    protected $attributes = [
        'pilgrim_count' => 0,
        'status' => self::STATUS_PLANNED,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $batch): void {
            if ($batch->return_date !== null
                && $batch->departure_date !== null
                && $batch->return_date->lt($batch->departure_date)) {
                throw new InvalidArgumentException('return_date must not be earlier than departure_date.');
            }

            if ($batch->pilgrim_count < 0) {
                throw new InvalidArgumentException('pilgrim_count must not be negative.');
            }

            if ($batch->capacity !== null && $batch->pilgrim_count > $batch->capacity) {
                throw new InvalidArgumentException('pilgrim_count must not exceed capacity.');
            }

            if (! array_key_exists((string) $batch->status, self::STATUSES)) {
                throw new InvalidArgumentException('The batch status is invalid.');
            }

            if ($batch->is_active) {
                $batch->disabled_at = null;
            } elseif ($batch->disabled_at === null) {
                $batch->disabled_at = now();
            }
        });

        static::deleting(function (self $batch): void {
            throw new LogicException('Umrah batches cannot be deleted; deactivate the batch instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'departure_date' => 'date',
            'return_date' => 'date',
            'capacity' => 'integer',
            'pilgrim_count' => 'integer',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function pilgrims(): HasMany
    {
        return $this->hasMany(Pilgrim::class, 'umrah_batch_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_active'), true);
    }

    /** @param Builder<self> $query */
    public function scopeAvailableForNewPilgrims(Builder $query): Builder
    {
        return $query->active()->whereIn('status', [self::STATUS_PLANNED, self::STATUS_OPEN]);
    }

    public function deactivate(): bool
    {
        return $this->forceFill([
            'is_active' => false,
            'disabled_at' => now(),
        ])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill([
            'is_active' => true,
            'disabled_at' => null,
        ])->save();
    }
}
