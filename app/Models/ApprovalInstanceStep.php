<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ApprovalInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_instance_id',
        'workflow_step_id',
        'step_order',
        'step_key',
        'label',
        'resolver_type',
        'approval_mode',
        'step_type',
        'is_required',
        'sla_minutes',
        'escalation_type',
        'approver_id',
        'original_approver_id',
        'approver_name',
        'approver_role',
        'office_id',
        'branch_id',
        'department_id',
        'status',
        'decision',
        'note',
        'acted_by_id',
        'acted_at',
        'assigned_at',
        'due_at',
        'sla_warning_at',
        'sla_warning_sent_at',
        'expired_at',
        'escalated_at',
        'completed_at',
        'context',
    ];

    protected $attributes = [
        'approval_mode' => 'sequential',
        'is_required' => true,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $step): void {
            if ($step->isDirty([
                'approval_instance_id',
                'step_order',
                'step_key',
                'label',
                'resolver_type',
                'approval_mode',
                'step_type',
                'is_required',
                'sla_minutes',
                'escalation_type',
                'approver_id',
                'original_approver_id',
                'approver_name',
                'approver_role',
                'office_id',
                'branch_id',
                'department_id',
                'context',
            ])) {
                throw ValidationException::withMessages([
                    'approval_step' => 'Approval step workflow, approver, and scope snapshot is immutable.',
                ]);
            }
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'approval_step' => 'Approval steps cannot be deleted after creation.',
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'approval_instance_id' => 'integer',
            'workflow_step_id' => 'integer',
            'step_order' => 'integer',
            'approver_id' => 'integer',
            'original_approver_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'is_required' => 'boolean',
            'sla_minutes' => 'integer',
            'approver_name' => 'string',
            'approver_role' => 'string',
            'acted_by_id' => 'integer',
            'acted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'sla_warning_sent_at' => 'datetime',
            'expired_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class);
    }
}
