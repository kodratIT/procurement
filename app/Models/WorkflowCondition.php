<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowConditionOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkflowCondition extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_step_id', 'field_key', 'operator', 'value'];

    protected static function booted(): void
    {
        static::saving(function (self $condition): void {
            if ($condition->workflowStep?->workflowVersion?->isUsed()) {
                throw ValidationException::withMessages(['workflow_conditions' => 'Conditions of a workflow version used by a purchase request are immutable.']);
            }
        });
        static::deleting(function (self $condition): void {
            if ($condition->workflowStep?->workflowVersion?->isUsed()) {
                throw ValidationException::withMessages(['workflow_conditions' => 'Conditions of a workflow version used by a purchase request cannot be deleted.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['operator' => WorkflowConditionOperator::class, 'value' => 'array'];
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }
}
