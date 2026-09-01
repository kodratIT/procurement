<?php

namespace App\Models;

use App\Models\Concerns\ContextScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Department extends Model
{
    use ContextScoped, HasFactory;

    protected $fillable = ['office_id', 'branch_id', 'code', 'name', 'is_active', 'disabled_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'disabled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $department): void {
            if ($department->is_active) {
                $department->disabled_at = null;
            } elseif ($department->disabled_at === null) {
                $department->disabled_at = now();
            }

            if ($department->branch_id !== null
                && ! DB::table('branches')
                    ->where('id', $department->branch_id)
                    ->where('office_id', $department->office_id)
                    ->exists()) {
                throw new \InvalidArgumentException('A department branch must belong to the same office.');
            }
        });

        static::deleting(function (self $department): void {
            if ($department->hasReferences()) {
                throw new \LogicException('Referenced departments cannot be deleted; deactivate the department instead.');
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function approverMappings(): HasMany
    {
        return $this->hasMany(ApproverMapping::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function deactivate(): bool
    {
        return $this->forceFill([
            'is_active' => false,
            'disabled_at' => now(),
        ])->save();
    }

    private function hasReferences(): bool
    {
        foreach (['user_assignments', 'purchase_requests'] as $table) {
            if (DB::table($table)->where('department_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
