<?php

namespace App\Models;

use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Office extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'disabled_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'disabled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $office): void {
            if ($office->is_active) {
                $office->disabled_at = null;
            } elseif ($office->disabled_at === null) {
                $office->disabled_at = now();
            }

            if (Auth::check()) {
                app(MultiOfficeAuthorization::class)->authorizeMutation(
                    Auth::user(),
                    $office,
                    ProcurementPermissions::MANAGE_MASTER_DATA,
                );
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function approverMappings(): HasMany
    {
        return $this->hasMany(ApproverMapping::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
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

    public function isReferenceFree(): bool
    {
        foreach (['branches', 'departments', 'cost_centers', 'user_assignments', 'purchase_requests'] as $table) {
            if (DB::table($table)->where('office_id', $this->getKey())->exists()) {
                return false;
            }
        }

        return ! DB::table('office_user')->where('office_id', $this->getKey())->exists();
    }
}
