<?php

namespace App\Services;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ActiveOfficeContext
{
    public const SESSION_KEY = 'active_office_id';

    private ?Office $office = null;

    /**
     * Resolve the office the authenticated user is currently acting as.
     *
     * Prefers the session selection, falling back to the primary assignment,
     * then to the first active assignment. The resolved office is always
     * re-validated against the user's current assignments so a revoked or
     * expired assignment cannot keep a stale office active.
     */
    public function current(): ?Office
    {
        if ($this->office !== null) {
            return $this->office;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $assignments = $this->activeAssignmentsQuery($user);
        $id = session(self::SESSION_KEY);

        $this->office = $id
            ? $assignments->where('office_id', $id)->with('office')->first()?->office
            : $assignments->orderByDesc('is_primary')->orderBy('office_id')->with('office')->first()?->office;

        return $this->office;
    }

    public function id(): ?int
    {
        return $this->current()?->getKey();
    }

    /**
     * Switch the active office. Only offices the user is currently assigned
     * to (and that are active) can be selected; anything else is a 403.
     */
    public function set(Office|int $office): Office
    {
        $office = $office instanceof Office ? $office : Office::findOrFail($office);
        $user = Auth::user();

        abort_unless($user instanceof User && $this->hasAccess($office), 403);

        session()->put(self::SESSION_KEY, $office->getKey());
        $this->office = $office;

        return $office;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->office = null;
    }

    public function hasAccess(?Office $office): bool
    {
        return $office !== null && Auth::user() instanceof User
            && $this->activeAssignmentsQuery(Auth::user())->where('office_id', $office->getKey())->exists();
    }

    /**
     * Offices the authenticated user may switch to right now.
     *
     * @return Collection<int, Office>
     */
    public function availableOffices(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return new Collection;
        }

        return $this->activeAssignmentsQuery($user)
            ->with('office')
            ->get()
            ->map(fn ($assignment) => $assignment->office)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function require(): Office
    {
        return $this->current() ?? throw new InvalidArgumentException('No active office is assigned to the authenticated user.');
    }

    private function activeAssignmentsQuery(User $user): HasMany
    {
        $today = Carbon::today();

        return $user->assignments()
            ->where('user_assignments.is_active', true)
            ->whereHas('office', fn ($query) => $query->where('offices.is_active', true)->whereNull('offices.disabled_at'))
            ->whereDate('valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }
}
