<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'workflow_version_id',
        'workflow_reference',
        'workflow_version',
        'status',
        'requester_id',
        'submitted_by_id',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'submitted_at',
        'context',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $instance): void {
            $snapshotFields = [
                'purchase_request_id',
                'workflow_version_id',
                'workflow_reference',
                'workflow_version',
                'requester_id',
                'submitted_by_id',
                'office_id',
                'branch_id',
                'department_id',
                'cost_center_id',
                'submitted_at',
                'context',
            ];

            if ($instance->isDirty($snapshotFields)) {
                throw ValidationException::withMessages([
                    'approval_instance' => 'Approval instance workflow and scope snapshot is immutable.',
                ]);
            }
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'approval_instance' => 'Approval instances cannot be deleted after creation.',
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'workflow_version_id' => 'integer',
            'workflow_version' => 'integer',
            'requester_id' => 'integer',
            'submitted_by_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'cost_center_id' => 'integer',
            'submitted_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStep::class)->orderBy('step_order')->orderBy('id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'in_progress'], true);
    }

    public function histories(): HasManyThrough
    {
        return $this->hasManyThrough(ApprovalHistory::class, ApprovalInstanceStep::class);
    }
}
