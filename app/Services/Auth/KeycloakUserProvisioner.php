<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;

class KeycloakUserProvisioner
{
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
        $sub = (string) ($claims['sub'] ?? '');
        if ($sub === '') {
            throw ValidationException::withMessages([
                'sub' => 'Keycloak subject is missing.',
            ]);
        }

        $email = isset($claims['email']) && $claims['email'] !== ''
            ? (string) $claims['email']
            : null;
        $name = (string) ($claims['name'] ?? $claims['preferred_username'] ?? $sub);

        return DB::transaction(function () use ($sub, $email, $name): User {
            $user = User::query()->where('keycloak_sub', $sub)->lockForUpdate()->first();

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
                $wasCreated = $user === null;
                $user ??= new User(['keycloak_sub' => $sub]);
                $user->fill([
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                ]);
                $user->save();
                DB::table(config('activitylog.table_name', 'activity_log'))->insert([
                    'log_name' => 'keycloak',
                    'event' => $wasCreated ? 'keycloak.user_provisioned' : 'keycloak.user_synchronised',
                    'description' => 'Keycloak user provisioning completed.',
                    'properties' => json_encode(['user_id' => $user->getKey()], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
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
}
