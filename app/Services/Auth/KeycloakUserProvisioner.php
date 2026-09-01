<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KeycloakUserProvisioner
{
    private const PROVISIONING_MODES = ['jit', 'pre-provisioned', 'hybrid'];

    /**
     * Provision a local user from an OIDC userinfo claim set.
     *
     * The Keycloak subject is the only identity key. Email is an attribute,
     * not an account lookup key, and may not be claimed by another subject.
     *
     * @param  array<string, mixed>  $claims
     */
    public function provision(array $claims): User
    {
        $sub = trim((string) ($claims['sub'] ?? ''));
        if ($sub === '') {
            throw ValidationException::withMessages([
                'sub' => 'Keycloak subject is missing.',
            ]);
        }

        $mode = str_replace('_', '-', strtolower((string) config('keycloak.provisioning_mode', 'hybrid')));
        if (! in_array($mode, self::PROVISIONING_MODES, true)) {
            throw new \InvalidArgumentException('Keycloak provisioning mode is invalid.');
        }

        $email = $this->nullableClaim($claims['email'] ?? null);
        $avatar = $this->nullableClaim($claims['picture'] ?? $claims['avatar'] ?? null);
        $name = trim((string) ($claims['name'] ?? ''))
            ?: trim((string) ($claims['preferred_username'] ?? ''))
            ?: $sub;
        $isActive = $this->booleanClaim($claims['is_active'] ?? $claims['enabled'] ?? null);
        $now = now();

        return DB::transaction(function () use ($sub, $email, $avatar, $name, $isActive, $mode, $now): User {
            $user = User::query()->where('keycloak_sub', $sub)->lockForUpdate()->first();

            if ($mode === 'pre-provisioned' && $user === null) {
                throw ValidationException::withMessages([
                    'sub' => 'Your Keycloak account has not been provisioned. Contact an administrator.',
                ]);
            }

            if ($email !== null) {
                $emailOwner = User::query()
                    ->where('email', $email)
                    ->where(function ($query) use ($sub) {
                        $query->whereNull('keycloak_sub')->orWhere('keycloak_sub', '!=', $sub);
                    })
                    ->exists();

                if ($emailOwner) {
                    throw ValidationException::withMessages([
                        'email' => 'This email address is already linked to another account.',
                    ]);
                }
            }

            try {
                $user ??= new User(['keycloak_sub' => $sub, 'is_active' => true]);
                $user->fill([
                    'name' => $name,
                    'email' => $email,
                    'avatar' => $avatar,
                    'last_login_at' => $now,
                    'last_token_sync_at' => $now,
                ]);
                if ($isActive !== null) {
                    $user->is_active = $isActive;
                }
                $user->save();
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000' || str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw ValidationException::withMessages([
                        'email' => 'The Keycloak account conflicts with an existing user.',
                    ]);
                }

                throw $exception;
            }

            return $user;
        });
    }

    private function nullableClaim(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function booleanClaim(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }
}
