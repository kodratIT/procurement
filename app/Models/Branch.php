<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['office_id', 'code', 'name', 'is_active', 'disabled_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'disabled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $branch): void {
            if ($branch->is_active) {
                $branch->disabled_at = null;
            } elseif ($branch->disabled_at === null) {
                $branch->disabled_at = now();
            }
        });

        static::deleting(function (self $branch): void {
            if ($branch->hasReferences()) {
                throw new \LogicException('Referenced branches cannot be deleted; deactivate the branch instead.');
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
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
        foreach (['departments', 'user_assignments', 'purchase_requests'] as $table) {
            if (DB::table($table)->where('branch_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
