<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ContextScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class Pilgrim extends Model
{
    use ContextScoped, HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_REGISTERED => 'Terdaftar',
        self::STATUS_CONFIRMED => 'Terkonfirmasi',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'office_id',
        'umrah_batch_id',
        'name',
        'passport_no',
        'phone',
        'status',
        'is_active',
        'disabled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_REGISTERED,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $pilgrim): void {
            $pilgrim->passport_no = strtoupper(trim((string) $pilgrim->passport_no));

            if (! array_key_exists((string) $pilgrim->status, self::STATUSES)) {
                throw new InvalidArgumentException('The pilgrim status is invalid.');
            }

            $batch = UmrahBatch::query()
                ->withoutGlobalScope('access_context')
                ->find($pilgrim->umrah_batch_id);

            if (! $batch instanceof UmrahBatch) {
                throw new InvalidArgumentException('A valid Umrah batch is required.');
            }

            if ((int) $pilgrim->office_id !== (int) $batch->office_id) {
                throw new InvalidArgumentException('The pilgrim office must match the batch office.');
            }

            if ($pilgrim->is_active && ! $batch->is_active) {
                throw new InvalidArgumentException('Active pilgrims cannot belong to an inactive batch.');
            }

            if ($pilgrim->is_active) {
                $pilgrim->disabled_at = null;
            } elseif ($pilgrim->disabled_at === null) {
                $pilgrim->disabled_at = now();
            }
        });

        static::deleting(function (self $pilgrim): void {
            throw new LogicException('Pilgrims cannot be deleted; deactivate the pilgrim instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'umrah_batch_id' => 'integer',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(UmrahBatch::class, 'umrah_batch_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_active'), true);
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
