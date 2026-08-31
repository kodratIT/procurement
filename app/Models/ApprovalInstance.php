<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
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

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
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
}
