<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuthorizationService
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function allows(User $user, string $permission, ?int $officeId = null, ?int $branchId = null, ?int $departmentId = null): bool
    {
        return $this->authorization->allows($user, $permission, array_filter([
            'office_id' => $officeId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function allowsAcrossAssignments(User $user, string $permission): bool
    {
        return $this->authorization->allowsAcrossAssignments($user, $permission);
    }

    /** @param Builder<object> $query */
    public function scopeAcrossAssignments(Builder $query, User $user, string $permission): Builder
    {
        return $this->authorization->scopeForUser($query, $user, $permission);
    }

    public function canAccessAssignment(User $user, UserAssignment $assignment): bool
    {
        return $this->authorization->canView($user, $assignment);
    }

    public function assertMutationContext(User $user, int $officeId, ?int $branchId = null, ?int $departmentId = null): void
    {
        if (! $this->allows($user, ProcurementPermissions::UPDATE, $officeId, $branchId, $departmentId)) {
            throw new AuthorizationException('The mutation is outside the active access context.');
        }
    }

    /** @param Builder<object> $query */
    public function scope(Builder $query, ?User $user = null, string $officeColumn = 'office_id', string $branchColumn = 'branch_id', string $departmentColumn = 'department_id'): Builder
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return $query->whereKey(0);
        }

        return $this->authorization->scopeForCurrentContext($query, $user);
    }

    public function canManageRecord(User $user, string $permission, object $record): bool
    {
        // Targeted authorization: any active assignment that grants the
        // permission and matches the record's office/branch/department scope.
        return $record instanceof Model
            && $this->authorization->allows($user, $permission, $record, acrossAssignments: true);
    }
}
