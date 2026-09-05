<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AccessContextService
{
    public const SESSION_KEY = 'active_context';

    public const LEGACY_SESSION_KEY = 'active_office_id';

    public const CONFIRMATION_SESSION_KEY = 'confirmed_mutation_context';

    /** @var array<string, int|null>|null */
    private ?array $storedContext = null;

    private ?UserAssignment $assignment = null;

    private ?int $resolvedUserId = null;

    private bool $resolved = false;

    public function current(): ?Office
    {
        return $this->assignment()?->office;
    }

    public function office(): ?Office
    {
        return $this->current();
    }

    public function branch(): ?Branch
    {
        return $this->assignment()?->branch;
    }

    public function department(): ?Department
    {
        return $this->assignment()?->department;
    }

    public function role(): ?Role
    {
        return $this->assignment()?->assignedRole;
    }

    public function roleName(): ?string
    {
        return $this->role()?->name;
    }

    public function id(): ?int
    {
        return $this->current()?->getKey();
    }

    public function assignment(): ?UserAssignment
    {
        $user = Auth::user();
        $userId = $user instanceof User ? $user->getKey() : null;

        if ($this->resolved && $this->resolvedUserId === $userId) {
            return $this->assignment;
        }

        $this->resolved = true;
        $this->resolvedUserId = $userId;

        if (! $user instanceof User || ! $user->is_active) {
            $this->assignment = null;

            return null;
        }

        $assignments = $this->activeAssignments($user);
        $stored = $this->storedContext();
        $query = clone $assignments;

        if ($stored !== null) {
            $query->whereKey($stored['assignment_id'] ?? 0);
        }

        $this->assignment = $query
            ->with([
                'office',
                'branch' => fn ($query) => $query->withoutGlobalScope('access_context'),
                'department' => fn ($query) => $query->withoutGlobalScope('access_context'),
                'assignedRole',
                'scopes',
            ])
            ->orderByDesc('is_primary')
            ->orderBy('office_id')
            ->orderBy('id')
            ->first();

        if ($this->assignment === null && $stored !== null) {
            $this->forgetStoredContext();
            $this->storedContext = null;
            $this->assignment = $assignments
                ->with([
                    'office',
                    'branch' => fn ($query) => $query->withoutGlobalScope('access_context'),
                    'department' => fn ($query) => $query->withoutGlobalScope('access_context'),
                    'assignedRole',
                    'scopes',
                ])
                ->orderByDesc('is_primary')
                ->orderBy('office_id')
                ->orderBy('id')
                ->first();
        }

        if ($this->assignment !== null) {
            $this->persistValidatedContext($this->assignment);
        }

        return $this->assignment;
    }

    public function currentAssignment(): ?UserAssignment
    {
        return $this->assignment();
    }

    /** @return Collection<int, UserAssignment> */
    public function allowedContexts(?User $user = null): Collection
    {
        $user ??= Auth::user();

        return $user instanceof User && $user->is_active
            ? $this->activeAssignments($user)->with(['office', 'branch', 'department', 'assignedRole', 'scopes'])->get()
            : collect();
    }

    /** @return Collection<int, UserAssignment> */
    public function allowedAssignments(?User $user = null): Collection
    {
        return $this->allowedContexts($user);
    }

    /** @return Collection<int, Office> */
    public function allowedOffices(?User $user = null): Collection
    {
        return $this->allowedContexts($user)->pluck('office')->filter()->unique('id')->values();
    }

    public function defaultContext(?User $user = null): ?UserAssignment
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        return $this->activeAssignments($user)
            ->with(['office', 'branch', 'department', 'assignedRole', 'scopes'])
            ->orderByDesc('is_primary')
            ->orderBy('office_id')
            ->orderBy('id')
            ->first();
    }

    public function defaultOffice(?User $user = null): ?Office
    {
        return $this->defaultContext($user)?->office;
    }

    public function isDefault(): bool
    {
        $active = $this->assignment();
        $default = $this->defaultContext();

        return $active !== null && $default !== null && $active->is($default);
    }

    public function set(Office|int $office): Office
    {
        $office = $office instanceof Office ? $office : Office::query()->findOrFail($office);
        $assignment = $this->allowedContexts()->firstWhere('office_id', $office->getKey());

        if (! $assignment instanceof UserAssignment) {
            abort(403, 'The selected office is not assigned to your account.');
        }

        return $this->setContext($assignment)->office;
    }

    /** @param array<string, int|null>|UserAssignment $context */
    public function setContext(array|UserAssignment $context): UserAssignment
    {
        $assignment = $context instanceof UserAssignment
            ? $this->allowedContexts()->first(fn (UserAssignment $candidate): bool => $candidate->is($context))
            : $this->findMatchingContext($context);

        if (! $assignment instanceof UserAssignment) {
            throw new InvalidArgumentException('The selected access context is not assigned to the authenticated user.');
        }

        $this->assignment = $assignment;
        $this->resolved = true;
        $this->persistValidatedContext($assignment);

        return $assignment;
    }

    public function confirmMutation(): void
    {
        $assignment = $this->assignment();

        if ($assignment === null) {
            throw new InvalidArgumentException('A valid active context is required before confirming a mutation.');
        }

        session()->put(self::CONFIRMATION_SESSION_KEY, $assignment->getKey());
    }

    public function hasAccess(?Office $office): bool
    {
        return $office !== null && $this->allowedOffices()->contains(
            fn (Office $allowed): bool => $allowed->is($office),
        );
    }

    public function mutationIsConfirmed(): bool
    {
        return $this->requiresConfirmation()
            && (int) session(self::CONFIRMATION_SESSION_KEY) === $this->assignment()?->getKey();
    }

    public function clear(): void
    {
        $this->storedContext = null;
        $this->assignment = null;
        $this->resolved = false;
        $this->forgetStoredContext();
    }

    public function can(string $permission): bool
    {
        return $this->assignment()?->allows($permission) ?? false;
    }

    public function canUseContext(UserAssignment $assignment): bool
    {
        return $this->allowedContexts()->contains(fn (UserAssignment $candidate): bool => $candidate->is($assignment));
    }

    public function requiresConfirmation(?UserAssignment $context = null): bool
    {
        $context ??= $this->assignment();
        $default = $this->defaultContext();

        return $context !== null
            && $default !== null
            && $context->office_id !== $default->office_id;
    }

    /** @return array<string, int|null>|null */
    public function snapshot(): ?array
    {
        $assignment = $this->assignment();

        if ($assignment === null) {
            return null;
        }

        return [
            'assignment_id' => (int) $assignment->getKey(),
            'office_id' => (int) $assignment->office_id,
            'branch_id' => $assignment->branch_id !== null ? (int) $assignment->branch_id : null,
            'department_id' => $assignment->department_id !== null ? (int) $assignment->department_id : null,
            'role_id' => $assignment->role_id !== null ? (int) $assignment->role_id : null,
        ];
    }

    public function require(): Office
    {
        return $this->current() ?? throw new InvalidArgumentException('No active office is assigned to the authenticated user.');
    }

    /** @return HasMany<UserAssignment> */
    private function activeAssignments(User $user): HasMany
    {
        return $user->assignments()->currentlyActive(Carbon::today());
    }

    /** @param array<string, int|null> $context */
    private function findMatchingContext(array $context): ?UserAssignment
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $query = $this->activeAssignments($user);

        if (isset($context['assignment_id']) && is_numeric($context['assignment_id'])) {
            return $query
                ->whereKey((int) $context['assignment_id'])
                ->with(['office', 'branch', 'department', 'assignedRole', 'scopes'])
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();
        }

        if (isset($context['office_id']) && is_numeric($context['office_id'])) {
            $query->where('office_id', (int) $context['office_id']);
        }

        foreach (['branch_id', 'department_id', 'role_id'] as $column) {
            if (array_key_exists($column, $context)) {
                $value = $context[$column];
                $query = $value === null
                    ? $query->whereNull($column)
                    : $query->where($column, (int) $value);
            }
        }

        return $query
            ->with(['office', 'branch', 'department', 'assignedRole', 'scopes'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /** @return array<string, int|null>|null */
    private function storedContext(): ?array
    {
        if ($this->storedContext !== null) {
            return $this->storedContext;
        }

        $stored = session(self::SESSION_KEY);

        if (is_array($stored) && isset($stored['office_id']) && is_numeric($stored['office_id'])) {
            return $this->storedContext = [
                'assignment_id' => isset($stored['assignment_id']) && is_numeric($stored['assignment_id']) ? (int) $stored['assignment_id'] : null,
                'office_id' => (int) $stored['office_id'],
                'branch_id' => array_key_exists('branch_id', $stored) && $stored['branch_id'] !== null ? (int) $stored['branch_id'] : null,
                'department_id' => array_key_exists('department_id', $stored) && $stored['department_id'] !== null ? (int) $stored['department_id'] : null,
                'role_id' => array_key_exists('role_id', $stored) && $stored['role_id'] !== null ? (int) $stored['role_id'] : null,
            ];
        }

        $legacyOfficeId = session(self::LEGACY_SESSION_KEY);

        if (is_numeric($legacyOfficeId)) {
            return $this->storedContext = [
                'assignment_id' => null,
                'office_id' => (int) $legacyOfficeId,
                'branch_id' => null,
                'department_id' => null,
                'role_id' => null,
            ];
        }

        return null;
    }

    private function persistValidatedContext(UserAssignment $assignment): void
    {
        $snapshot = [
            'assignment_id' => (int) $assignment->getKey(),
            'office_id' => (int) $assignment->office_id,
            'branch_id' => $assignment->branch_id !== null ? (int) $assignment->branch_id : null,
            'department_id' => $assignment->department_id !== null ? (int) $assignment->department_id : null,
            'role_id' => $assignment->role_id !== null ? (int) $assignment->role_id : null,
        ];

        $this->storedContext = $snapshot;
        session()->put(self::SESSION_KEY, $snapshot);
        session()->put(self::LEGACY_SESSION_KEY, $snapshot['office_id']);
    }

    private function forgetStoredContext(): void
    {
        session()->forget([
            self::SESSION_KEY,
            self::LEGACY_SESSION_KEY,
            self::CONFIRMATION_SESSION_KEY,
        ]);
    }
}
