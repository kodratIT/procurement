<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_version_id', 'sequence', 'name', 'step_type', 'approval_mode', 'resolver_type', 'required_permission', 'is_required', 'sla_minutes', 'escalation_type', 'settings'];

    protected static function booted(): void
    {
        static::saving(function (self $step): void {
            if ($step->workflowVersion?->isUsed()) {
                throw ValidationException::withMessages(['workflow_steps' => 'Steps of a workflow version used by a purchase request are immutable.']);
            }
        });
        static::deleting(function (self $step): void {
            if ($step->workflowVersion?->isUsed()) {
                throw ValidationException::withMessages(['workflow_steps' => 'Steps of a workflow version used by a purchase request cannot be deleted.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['step_type' => WorkflowStepType::class, 'approval_mode' => WorkflowApprovalMode::class, 'is_required' => 'boolean', 'sla_minutes' => 'integer', 'settings' => 'array'];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class);
    }
}
