<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_DRAFT = 'draft';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
        self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'office_id',
        'cost_center_id',
        'year',
        'amount',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'amount' => '0.00',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $budget): void {
            if (! in_array($budget->status, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The budget status is invalid.',
                ]);
            }

            if ((float) $budget->amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The budget amount must not be negative.',
                ]);
            }

            if ((int) $budget->year < 2000 || (int) $budget->year > 2200) {
                throw ValidationException::withMessages([
                    'year' => 'The budget year is invalid.',
                ]);
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BudgetReservation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('finance')
            ->logOnly(['office_id', 'cost_center_id', 'year', 'amount', 'status'])
            ->logOnlyDirty();
    }
}
