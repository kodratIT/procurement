<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ApprovalInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_instance_id',
        'step_order',
        'step_key',
        'label',
        'resolver_type',
        'approver_id',
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
        'context',
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
                'approver_id',
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
            'step_order' => 'integer',
            'approver_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'approver_name' => 'string',
            'approver_role' => 'string',
            'acted_by_id' => 'integer',
            'acted_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_id');
    }
}
