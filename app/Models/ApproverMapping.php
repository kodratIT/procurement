<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ApproverMappingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApproverMapping extends Model
{
    /** @use HasFactory<ApproverMappingFactory> */
    use HasFactory, LogsActivity;

    public const RESOLVER_TYPES = [
        'role_in_request_office',
        'role_in_budget_owner_office',
        'specific_user',
        'department_head',
        'branch_head',
        'cost_center_owner',
        'nominal_role',
    ];

    public const SCOPE_SOURCES = [
        'request_office',
        'budget_owner_office',
        'request_branch',
        'request_department',
        'request_cost_center',
        'configured',
    ];

    public const FALLBACK_TYPES = ['block', 'role', 'user'];

    protected $fillable = [
        'workflow_step_id',
        'resolver_type',
        'role_id',
        'user_id',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'scope_source',
        'fallback_type',
        'fallback_role_id',
        'fallback_user_id',
        'priority',
        'allow_self_approval',
        'valid_from',
        'valid_until',
        'is_active',
        'settings',
    ];

    protected $attributes = [
        'scope_source' => 'request_office',
        'fallback_type' => 'block',
        'priority' => 0,
        'allow_self_approval' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $mapping): void {
            if (! in_array($mapping->resolver_type, self::RESOLVER_TYPES, true)) {
                throw ValidationException::withMessages(['resolver_type' => 'The approver resolver type is invalid.']);
            }

            if (! in_array($mapping->scope_source, self::SCOPE_SOURCES, true)) {
                throw ValidationException::withMessages(['scope_source' => 'The approver scope source is invalid.']);
            }

            if (! in_array($mapping->fallback_type, self::FALLBACK_TYPES, true)) {
                throw ValidationException::withMessages(['fallback_type' => 'The approver fallback type is invalid.']);
            }

            if ($mapping->role_id === null && $mapping->user_id === null) {
                throw ValidationException::withMessages(['role_id' => 'A role or specific user is required for an approver mapping.']);
            }

            if ($mapping->valid_until !== null && $mapping->valid_from !== null && $mapping->valid_until->lt($mapping->valid_from)) {
                throw ValidationException::withMessages(['valid_until' => 'The mapping end date must not be earlier than its start date.']);
            }

            if ($mapping->fallback_type === 'role' && $mapping->fallback_role_id === null) {
                throw ValidationException::withMessages(['fallback_role_id' => 'A fallback role is required when role fallback is selected.']);
            }

            if ($mapping->fallback_type === 'user' && $mapping->fallback_user_id === null) {
                throw ValidationException::withMessages(['fallback_user_id' => 'A fallback user is required when user fallback is selected.']);
            }

            self::validateOrganizationContext($mapping);
        });
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    public function fallbackRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'fallback_role_id');
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }

    public function scopeActiveAt(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= Carbon::today();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('valid_from')
                ->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date));
    }

    public function isActiveAt(?CarbonInterface $date = null): bool
    {
        $date ??= Carbon::today();

        return $this->is_active
            && ($this->valid_from === null || $this->valid_from->lte($date))
            && ($this->valid_until === null || $this->valid_until->gte($date));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('workflow')
            ->logOnly([
                'workflow_step_id',
                'resolver_type',
                'role_id',
                'user_id',
                'office_id',
                'branch_id',
                'department_id',
                'cost_center_id',
                'scope_source',
                'fallback_type',
                'fallback_role_id',
                'fallback_user_id',
                'priority',
                'allow_self_approval',
                'valid_from',
                'valid_until',
                'is_active',
                'settings',
            ])
            ->logOnlyDirty();
    }

    protected function casts(): array
    {
        return [
            'workflow_step_id' => 'integer',
            'role_id' => 'integer',
            'user_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'cost_center_id' => 'integer',
            'fallback_role_id' => 'integer',
            'fallback_user_id' => 'integer',
            'priority' => 'integer',
            'allow_self_approval' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    private static function validateOrganizationContext(self $mapping): void
    {
        if ($mapping->branch_id !== null && ! Branch::query()
            ->withoutGlobalScopes()
            ->whereKey($mapping->branch_id)
            ->where('office_id', $mapping->office_id)
            ->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'The mapping branch must belong to the selected office.']);
        }

        if ($mapping->department_id !== null && ! Department::query()
            ->withoutGlobalScopes()
            ->whereKey($mapping->department_id)
            ->where('office_id', $mapping->office_id)
            ->exists()) {
            throw ValidationException::withMessages(['department_id' => 'The mapping department must belong to the selected office.']);
        }

        if ($mapping->cost_center_id !== null && ! CostCenter::query()
            ->withoutGlobalScopes()
            ->whereKey($mapping->cost_center_id)
            ->where('office_id', $mapping->office_id)
            ->exists()) {
            throw ValidationException::withMessages(['cost_center_id' => 'The mapping cost center must belong to the selected office.']);
        }
    }
}
