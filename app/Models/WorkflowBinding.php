<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkflowBinding extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'office_id', 'branch_id', 'department_id', 'category_id', 'cost_center_id', 'transaction_type', 'minimum_amount', 'maximum_amount', 'priority', 'conditions', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (self $binding): void {
            if ($binding->workflow?->versions()->whereHas('approvalInstances')->exists()) {
                throw ValidationException::withMessages(['workflow_bindings' => 'Bindings for a workflow version used by a purchase request are immutable.']);
            }
        });
        static::deleting(function (self $binding): void {
            if ($binding->workflow?->versions()->whereHas('approvalInstances')->exists()) {
                throw ValidationException::withMessages(['workflow_bindings' => 'Bindings for a workflow version used by a purchase request cannot be deleted.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['minimum_amount' => 'decimal:2', 'maximum_amount' => 'decimal:2', 'priority' => 'integer', 'conditions' => 'array', 'is_active' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategory::class, 'category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
