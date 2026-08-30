<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserAssignment extends Model
{
    /** @use HasFactory<UserAssignmentFactory> */
    use HasFactory, LogsActivity;

    public const DEFAULT_ROLE = 'Viewer';

    protected $fillable = [
        'user_id',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'role',
        'role_id',
        'valid_from',
        'valid_until',
        'is_primary',
        'is_active',
        'disabled_at',
    ];

    protected $attributes = [
        'is_primary' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $assignment->syncRole();
            $assignment->validatePeriod();
            $assignment->validateOrganizationContext();
            $assignment->rejectOverlappingAssignment();

            if ($assignment->is_active) {
                $assignment->disabled_at = null;
            } elseif ($assignment->disabled_at === null) {
                $assignment->disabled_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(AssignmentScope::class, 'assignment_id');
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForRole(Builder $query, int $roleId): Builder
    {
        return $query->where('role_id', $roleId);
    }

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query
            ->where(function (Builder $query) use ($permission): void {
                $query->whereHas('assignedRole', fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereHas('permissions', fn (Builder $query): Builder => $query
                        ->where('permissions.name', $permission)
                        ->where('permissions.guard_name', 'web')))
                    ->orWhereHas('permissionOverrides', fn (Builder $query): Builder => $query
                        ->where('effect', AssignmentPermissionOverride::ALLOW)
                        ->whereHas('permission', fn (Builder $query): Builder => $query
                            ->where('name', $permission)
                            ->where('guard_name', 'web')));
            })
            ->whereDoesntHave('permissionOverrides', fn (Builder $query): Builder => $query
                ->where('effect', AssignmentPermissionOverride::DENY)
                ->whereHas('permission', fn (Builder $query): Builder => $query
                    ->where('name', $permission)
                    ->where('guard_name', 'web')));
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(AssignmentPermissionOverride::class, 'assignment_id');
    }

    public function scopeCurrentlyActive(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= Carbon::today();

        return $query
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date))
            ->whereHas('office', fn (Builder $query): Builder => $query
                ->where('offices.is_active', true)
                ->whereNull('offices.disabled_at'));
    }

    public function isCurrentlyActive(?CarbonInterface $date = null): bool
    {
        $date ??= Carbon::today();

        if (! $this->is_active || $this->disabled_at !== null
            || $this->valid_from === null || $this->valid_from->gt($date)
            || ($this->valid_until !== null && $this->valid_until->lt($date))) {
            return false;
        }

        return $this->office()->where('is_active', true)->whereNull('disabled_at')->exists();
    }

    public function allows(string $permission, ?CarbonInterface $date = null): bool
    {
        if (! $this->isCurrentlyActive($date)) {
            return false;
        }

        $override = $this->permissionOverrides()
            ->whereHas('permission', fn (Builder $query): Builder => $query->where('name', $permission)->where('guard_name', 'web'))
            ->value('effect');

        if ($override !== null) {
            return $override === AssignmentPermissionOverride::ALLOW;
        }

        $role = $this->assignedRole()->where('is_active', true)->first();

        return $role !== null && $role->hasPermissionTo($permission, 'web');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly([
                'user_id',
                'office_id',
                'branch_id',
                'department_id',
                'cost_center_id',
                'role',
                'role_id',
                'valid_from',
                'valid_until',
                'is_primary',
                'is_active',
                'disabled_at',
            ])
            ->logOnlyDirty();
    }

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    private function syncRole(): void
    {
        $role = $this->role_id !== null
            ? Role::query()->find($this->role_id)
            : Role::query()->where('name', $this->role ?? self::DEFAULT_ROLE)->where('guard_name', 'web')->first();

        if ($role === null && $this->role_id === null && ($this->role ?? self::DEFAULT_ROLE) === self::DEFAULT_ROLE) {
            $role = Role::query()->create(['name' => self::DEFAULT_ROLE, 'guard_name' => 'web']);
        }

        if ($role === null) {
            throw new InvalidArgumentException('The assignment role does not exist.');
        }

        $this->role_id = $role->getKey();
        $this->role = $role->name;
    }

    private function validatePeriod(): void
    {
        if ($this->valid_until !== null && $this->valid_from !== null && $this->valid_until->lt($this->valid_from)) {
            throw new InvalidArgumentException('valid_until must not be earlier than valid_from.');
        }
    }

    private function validateOrganizationContext(): void
    {
        if ($this->branch_id !== null && ! Branch::query()
            ->withoutGlobalScope('access_context')
            ->whereKey($this->branch_id)
            ->where('office_id', $this->office_id)
            ->exists()) {
            throw new InvalidArgumentException('An assignment branch must belong to the same office.');
        }

        if ($this->department_id !== null) {
            $department = Department::query()->find($this->department_id);

            if ($department === null || $department->office_id !== $this->office_id) {
                throw new InvalidArgumentException('An assignment department must belong to the same office.');
            }

            if ($department->branch_id !== null && $department->branch_id !== $this->branch_id) {
                throw new InvalidArgumentException('An assignment department must belong to its selected branch.');
            }
        }

        if ($this->cost_center_id !== null && ! CostCenter::query()
            ->whereKey($this->cost_center_id)
            ->where('office_id', $this->office_id)
            ->exists()) {
            throw new InvalidArgumentException('An assignment cost center must belong to the same office.');
        }
    }

    private function rejectOverlappingAssignment(): void
    {
        if (! $this->is_active || $this->role_id === null || $this->valid_from === null) {
            return;
        }

        $query = static::query()
            ->where('user_id', $this->user_id)
            ->where('office_id', $this->office_id)
            ->where('role_id', $this->role_id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $this->valid_until ?? '9999-12-31')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $this->valid_from));

        foreach (['branch_id', 'department_id', 'cost_center_id'] as $column) {
            $value = $this->{$column};
            $query = $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('An active assignment with the same role and context overlaps this validity period.');
        }
    }
}
