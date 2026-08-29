<?php

namespace App\Services;

use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ActiveOfficeContext
{
    public const SESSION_KEY = 'active_office_id';

    private ?Office $office = null;

    public function current(): ?Office
    {
        if ($this->office !== null) {
            return $this->office;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $today = Carbon::today();
        $assignments = $user->assignments()
            ->where('user_assignments.is_active', true)
            ->whereHas('office', fn ($query) => $query->where('offices.is_active', true)->whereNull('offices.disabled_at'))
            ->whereDate('valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));

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
            && Auth::user()->assignments()->where('office_id', $office->getKey())->where('is_active', true)
                ->whereDate('valid_from', '<=', Carbon::today())
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', Carbon::today()))
                ->whereHas('office', fn ($query) => $query->where('is_active', true)->whereNull('disabled_at'))
                ->exists();
    }

    public function require(): Office
    {
        return $this->current() ?? throw new InvalidArgumentException('No active office is assigned to the authenticated user.');
    }
}
