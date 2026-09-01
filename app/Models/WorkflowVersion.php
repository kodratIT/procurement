<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowConditionOperator;
use App\Enums\WorkflowVersionStatus;
use App\Services\WorkflowBindingSelector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowVersion extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'version_number', 'status', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return [
            'status' => WorkflowVersionStatus::class,
            'version_number' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if ($version->exists && $version->isUsed() && $version->isDirty()) {
                throw ValidationException::withMessages(['workflow_version' => 'A workflow version used by a purchase request is immutable.']);
            }
        });
        static::deleting(function (self $version): void {
            if ($version->isUsed()) {
                throw ValidationException::withMessages(['workflow_version' => 'A workflow version used by a purchase request cannot be deleted.']);
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sequence');
    }

    public function approvalInstances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class);
    }

    public function isUsed(): bool
    {
        return $this->approvalInstances()->exists();
    }

    public function activate(): bool
    {
        if ($this->isUsed()) {
            throw ValidationException::withMessages(['workflow_version' => 'A workflow version used by a purchase request cannot be changed.']);
        }

        return DB::transaction(function (): bool {
            app(WorkflowBindingSelector::class)->validate($this->workflow);
            $this->validateDefinition();
            $this->workflow()->whereKey($this->workflow_id)->update(['is_active' => true]);
            self::query()->where('workflow_id', $this->workflow_id)->whereKeyNot($this->getKey())->where('status', WorkflowVersionStatus::Active->value)->update([
                'status' => WorkflowVersionStatus::Retired->value,
                'retired_at' => now(),
            ]);

            return $this->update([
                'status' => WorkflowVersionStatus::Active,
                'effective_from' => $this->effective_from ?? now(),
                'activated_at' => now(),
                'retired_at' => null,
            ]);
        });
    }

    public function retire(): bool
    {
        return $this->update(['status' => WorkflowVersionStatus::Retired, 'retired_at' => now()]);
    }

    private function validateDefinition(): void
    {
        $sequences = $this->steps()->pluck('sequence')->all();
        sort($sequences);
        $expected = range(1, count($sequences));

        if ($sequences !== $expected || $sequences === []) {
            throw ValidationException::withMessages(['workflow_steps' => 'Workflow steps must be non-empty and consecutively ordered from 1.']);
        }

        foreach ($this->steps()->with('conditions')->get() as $step) {
            foreach ($step->conditions as $condition) {
                $operator = $condition->getRawOriginal('operator');
                $value = $condition->value;
                if ($condition->field_key === '' || ! in_array($operator, array_column(WorkflowConditionOperator::cases(), 'value'), true)) {
                    throw ValidationException::withMessages(['workflow_conditions' => 'Workflow conditions must have a valid field and operator.']);
                }
                if (($operator === 'between' && (! is_array($value) || count($value) !== 2 || $value[0] > $value[1]))
                    || ($operator === 'in' && (! is_array($value) || $value === []))
                    || ($operator !== 'between' && $operator !== 'in' && $value === [])) {
                    throw ValidationException::withMessages(['workflow_conditions' => 'Workflow condition values are invalid.']);
                }
            }
        }

        if ($this->effective_from !== null && $this->effective_until !== null && $this->effective_until->lte($this->effective_from)) {
            throw ValidationException::withMessages(['effective_until' => 'The effective end must be after the effective start.']);
        }
    }
}
