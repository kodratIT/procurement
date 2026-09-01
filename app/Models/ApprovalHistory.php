<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ApprovalHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_instance_step_id',
        'user_id',
        'role_id',
        'office_id',
        'branch_id',
        'department_id',
        'action',
        'notes',
        'acted_at',
        'workflow_version',
        'ip_address',
        'device',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'approval_instance_step_id' => 'integer',
            'user_id' => 'integer',
            'role_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'workflow_version' => 'integer',
            'acted_at' => 'datetime',
            'context' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ValidationException::withMessages([
                'approval_history' => 'Approval histories are immutable.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'approval_history' => 'Approval histories cannot be deleted.',
            ]);
        });
    }

    public function approvalInstanceStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstanceStep::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
