<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'avatar', 'last_login_at', 'last_token_sync_at', 'is_active', 'keycloak_sub'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasActiveAssignment();
    }

    public function hasActiveAssignment(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->assignments()->where('is_active', true)
            ->whereDate('valid_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()))
            ->whereHas('office', fn ($query) => $query->where('is_active', true)->whereNull('disabled_at'))
            ->exists();
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('keycloak_sub') && $user->getOriginal('keycloak_sub') !== null) {
                throw new \LogicException('The Keycloak subject is immutable.');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_token_sync_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
