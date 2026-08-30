<?php

namespace App\Models;

use App\Models\Concerns\ContextScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CostCenter extends Model
{
    use ContextScoped, HasFactory;

    protected $fillable = ['office_id', 'code', 'name', 'is_active', 'disabled_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'disabled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $costCenter): void {
            if ($costCenter->is_active) {
                $costCenter->disabled_at = null;
            } elseif ($costCenter->disabled_at === null) {
                $costCenter->disabled_at = now();
            }
        });

        static::deleting(function (self $costCenter): void {
            if ($costCenter->hasReferences()) {
                throw new \LogicException('Referenced cost centers cannot be deleted; deactivate the cost center instead.');
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
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
        foreach (['user_assignments', 'purchase_requests'] as $table) {
            if (DB::table($table)->where('cost_center_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
