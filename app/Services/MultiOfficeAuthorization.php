<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ProcurementPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MultiOfficeAuthorization
{
    public function __construct(private readonly AccessContextService $context) {}

    public function allows(User $user, string $permission, Model|array|null $subject = null): bool
    {
        $assignment = $this->assignmentForUser($user, $subject);

        if ($assignment === null && ! $user->assignments()->currentlyActive()->exists()) {
            return $user->can($permission);
        }

        return $assignment !== null
            && $assignment->allows($permission)
            && $this->matchesSubject($assignment, $subject);
    }

    public function can(User $user, string $permission, Model|array|null $subject = null): bool
    {
        return $this->allows($user, $permission, $subject);
    }

    public function canView(User $user, Model|array|null $subject = null): bool
    {
        return $this->allows($user, ProcurementPermissions::VIEW, $subject);
    }

    public function canCreate(User $user, Model|array|null $subject = null): bool
    {
        return $this->allows($user, ProcurementPermissions::CREATE, $subject);
    }

    public function canUpdate(User $user, Model|array|null $subject = null, bool $confirmed = false): bool
    {
        return $this->canMutate($user, $subject, ProcurementPermissions::UPDATE, $confirmed);
    }

    public function canDelete(User $user, Model|array|null $subject = null, bool $confirmed = false): bool
    {
        return $this->canMutate($user, $subject, ProcurementPermissions::DELETE, $confirmed);
    }

    public function mutationPermission(Model $subject): string
    {
        return in_array($subject::class, [
            Branch::class,
            CostCenter::class,
            Department::class,
            Office::class,
            UserAssignment::class,
        ], true)
            ? ProcurementPermissions::MANAGE_MASTER_DATA
            : ($subject->exists ? ProcurementPermissions::UPDATE : ProcurementPermissions::CREATE);
    }

    public function canMutate(
        User $user,
        Model|array|null $subject,
        string $permission,
        bool $confirmed = false,
    ): bool {
        if (! $this->allows($user, $permission, $subject)) {
            return false;
        }

        return ! $this->requiresConfirmation($user, $subject)
            || $confirmed
            || ($user->is(auth()->user()) && $this->context->mutationIsConfirmed());
    }

    public function requiresConfirmation(User $user, Model|array|null $subject = null): bool
    {
        $officeId = $this->subjectValue($subject, 'office_id') ?? $this->context->id();
        $defaultOfficeId = $this->context->defaultOffice($user)?->getKey();

        return $officeId !== null && $defaultOfficeId !== null && (int) $officeId !== (int) $defaultOfficeId;
    }

    public function authorizeMutation(
        User $user,
        Model|array|null $subject,
        string $permission,
        bool $confirmed = false,
    ): void {
        if (! $this->allows($user, $permission, $subject)) {
            throw new AuthorizationException('The active assignment does not authorize this mutation.');
        }

        if ($this->requiresConfirmation($user, $subject)
            && ! $confirmed
            && ! ($user->is(auth()->user()) && $this->context->mutationIsConfirmed())) {
            throw new AuthorizationException('Confirm this mutation in the non-default office context before continuing.');
        }
    }

    /** @return Builder<Model> */
    public function scopeForCurrentContext(
        Builder $query,
        ?User $user = null,
        string $permission = ProcurementPermissions::VIEW,
    ): Builder {
        $user ??= auth()->user();
        $assignment = $user instanceof User ? $this->assignmentForUser($user) : null;

        if ($assignment === null || ! $assignment->allows($permission)) {
            return $query->whereKey(0);
        }

        return $this->applyAssignmentScope($query, $assignment);
    }

    /** @return Builder<Model> */
    public function scopeForUser(
        Builder $query,
        User $user,
        string $permission = ProcurementPermissions::VIEW,
    ): Builder {
        $assignments = $this->context->allowedAssignments($user)
            ->filter(fn (UserAssignment $assignment): bool => $assignment->allows($permission));

        if ($assignments->isEmpty()) {
            return $query->whereKey(0);
        }

        $model = $query->getModel();
        if (! $this->hasColumn($model, 'office_id')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($assignments): void {
            foreach ($assignments as $assignment) {
                $query->orWhere(function (Builder $query) use ($assignment): void {
                    $this->applyAssignmentScope($query, $assignment, nested: true);
                });
            }
        });
    }

    /** @return Builder<Model> */
    public function applyAssignmentScope(
        Builder $query,
        UserAssignment $assignment,
        bool $nested = false,
    ): Builder {
        $model = $query->getModel();
        $prefix = $model->getTable().'.';

        if ($this->hasColumn($model, 'office_id')) {
            $query->where($prefix.'office_id', $assignment->office_id);
        }

        foreach (['branch_id', 'department_id'] as $column) {
            if (! $this->hasColumn($model, $column) || $assignment->{$column} === null) {
                continue;
            }

            $query->where($prefix.$column, $assignment->{$column});
        }

        return $query;
    }

    private function assignmentForUser(User $user, Model|array|null $subject = null): ?UserAssignment
    {
        $active = $user->is(auth()->user()) ? $this->context->assignment() : $this->context->defaultContext($user);

        if ($active === null || ! $this->matchesSubject($active, $subject)) {
            return null;
        }

        return $active;
    }

    private function matchesSubject(UserAssignment $assignment, Model|array|null $subject): bool
    {
        if ($subject === null) {
            return true;
        }

        $officeId = $this->subjectValue($subject, 'office_id');
        $branchId = $this->subjectValue($subject, 'branch_id');
        $departmentId = $this->subjectValue($subject, 'department_id');

        if ($officeId !== null && (int) $officeId !== (int) $assignment->office_id) {
            return false;
        }

        foreach ([['branch_id', $branchId, 'branch'], ['department_id', $departmentId, 'department']] as [$column, $value, $scopeType]) {
            if ($value !== null && $assignment->{$column} !== null && (int) $value !== (int) $assignment->{$column}) {
                return false;
            }

            if ($this->hasRestrictedScope($assignment, $scopeType, $value)) {
                return false;
            }
        }

        return ! $this->hasRestrictedScope($assignment, 'office', $officeId);
    }

    private function hasRestrictedScope(UserAssignment $assignment, string $type, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return $assignment->relationLoaded('scopes')
            && $assignment->scopes->where('scope_type', $type)->contains(
                fn ($scope): bool => (int) $scope->scope_id !== (int) $value,
            );
    }

    private function subjectValue(Model|array|null $subject, string $key): mixed
    {
        if ($subject instanceof Office && $key === 'office_id') {
            return $subject->getKey();
        }

        if ($subject instanceof Model) {
            return $subject->getAttribute($key);
        }

        return is_array($subject) ? ($subject[$key] ?? null) : null;
    }

    private function hasColumn(Model $model, string $column): bool
    {
        return in_array($column, $model->getFillable(), true)
            || array_key_exists($column, $model->getAttributes());
    }
}
