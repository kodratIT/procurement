<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignmentBulkService
{
    /**
     * Create many assignments for one user in a single transaction.
     *
     * @param  list<array{
     *     office_id: int|string,
     *     role?: string|null,
     *     branch_id?: int|string|null,
     *     department_id?: int|string|null,
     *     cost_center_id?: int|string|null,
     *     valid_from: string,
     *     valid_until?: string|null,
     *     is_primary?: bool,
     *     is_active?: bool,
     * }>  $rows
     * @return EloquentCollection<int, UserAssignment>
     */
    public function createMany(User|int $user, array $rows): EloquentCollection
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);

        if ($rows === []) {
            throw new InvalidArgumentException('At least one assignment row is required.');
        }

        return DB::transaction(function () use ($user, $rows): EloquentCollection {
            $created = new EloquentCollection;

            foreach ($rows as $row) {
                $created->push($this->createSingle($user, $row));
            }

            if ($created->contains(fn (UserAssignment $a): bool => (bool) $a->is_primary)) {
                $this->ensureSinglePrimary($user, $created);
            }

            return $created;
        });
    }

    /**
     * Extend (or shorten) the validity of a set of assignments.
     */
    public function extendValidity(Collection|EloquentCollection $assignments, ?string $validFrom = null, ?string $validUntil = null): int
    {
        return DB::transaction(function () use ($assignments, $validFrom, $validUntil): int {
            $updated = 0;

            foreach ($assignments as $assignment) {
                $changes = [];

                if ($validFrom !== null && $validFrom !== '' && $assignment->valid_from->toDateString() !== $validFrom) {
                    $changes['valid_from'] = $validFrom;
                }

                if ($validUntil !== null && $validUntil !== '' && $assignment->valid_until?->toDateString() !== $validUntil) {
                    $changes['valid_until'] = $validUntil;
                }

                if ($changes === []) {
                    continue;
                }

                if (isset($changes['valid_from'], $changes['valid_until'])
                    && $changes['valid_until'] < $changes['valid_from']) {
                    throw new InvalidArgumentException(
                        'valid_until must not be earlier than valid_from for assignment #'.$assignment->getKey().'.'
                    );
                }

                $assignment->update($changes);
                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Ensure only one assignment is primary for the user.
     * The $keep collection wins; every other primary assignment of the user is demoted.
     *
     * @param  EloquentCollection<int, UserAssignment>|Collection<int, UserAssignment>  $keep
     */
    public function ensureSinglePrimary(User|int $user, Collection|EloquentCollection $keep): void
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);

        $keepIds = $keep->map(fn (UserAssignment $a): int => (int) $a->getKey())->all();

        $newPrimaryId = $keep->first(fn (UserAssignment $a): bool => (bool) $a->is_primary)?->getKey();

        if ($newPrimaryId === null) {
            throw new InvalidArgumentException('A primary assignment must be selected.');
        }

        DB::transaction(function () use ($user, $keepIds, $newPrimaryId): void {
            UserAssignment::query()
                ->where('user_id', $user->getKey())
                ->where('is_primary', true)
                ->whereNotIn('id', $keepIds)
                ->update(['is_primary' => false]);

            UserAssignment::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($newPrimaryId)
                ->whereIn('id', $keepIds)
                ->update(['is_primary' => false]);
        });
    }

    private function createSingle(User $user, array $row): UserAssignment
    {
        $validFrom = (string) ($row['valid_from'] ?? '');
        $validUntil = isset($row['valid_until']) && $row['valid_until'] !== null && $row['valid_until'] !== ''
            ? (string) $row['valid_until']
            : null;

        if ($validFrom === '') {
            throw new InvalidArgumentException('valid_from is required.');
        }

        if ($validUntil !== null && $validUntil < $validFrom) {
            throw new InvalidArgumentException(
                "valid_until ({$validUntil}) must not be earlier than valid_from ({$validFrom})."
            );
        }

        return UserAssignment::create([
            'user_id' => $user->getKey(),
            'office_id' => $row['office_id'],
            'role' => $row['role'] ?? UserAssignment::DEFAULT_ROLE,
            'branch_id' => $row['branch_id'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'cost_center_id' => $row['cost_center_id'] ?? null,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'is_primary' => (bool) ($row['is_primary'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);
    }
}
