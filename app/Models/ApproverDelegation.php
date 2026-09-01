<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ApproverDelegationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApproverDelegation extends Model
{
    /** @use HasFactory<ApproverDelegationFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['delegator_id', 'delegate_id', 'valid_from', 'valid_until', 'reason', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $delegation): void {
            if ($delegation->delegator_id === $delegation->delegate_id) {
                throw ValidationException::withMessages(['delegate_id' => 'An approver cannot delegate to themselves.']);
            }

            if ($delegation->valid_until !== null && $delegation->valid_from !== null && $delegation->valid_until->lt($delegation->valid_from)) {
                throw ValidationException::withMessages(['valid_until' => 'The delegation end date must not be earlier than its start date.']);
            }

            if (blank($delegation->reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for approver delegation.']);
            }
        });
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function scopeActiveAt(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= Carbon::today();

        return $query
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_until', '>=', $date);
    }

    public function isActiveAt(?CarbonInterface $date = null): bool
    {
        $date ??= Carbon::today();

        return $this->is_active
            && ($this->valid_from === null || $this->valid_from->lte($date))
            && ($this->valid_until === null || $this->valid_until->gte($date));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('workflow')
            ->logOnly(['delegator_id', 'delegate_id', 'valid_from', 'valid_until', 'reason', 'is_active'])
            ->logOnlyDirty();
    }

    protected function casts(): array
    {
        return [
            'delegator_id' => 'integer',
            'delegate_id' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
