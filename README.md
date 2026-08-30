# Umrah Procurement

Laravel 13 + Filament 5 procurement platform for umrah travel operators. Multi-office RBAC with
Keycloak SSO (OIDC Authorization Code + PKCE), role/permission-based authorization, audit logging,
and CSV export — all free/open-source plugins.

## Tech stack

| Layer      | Choice                                        |
|------------|-----------------------------------------------|
| Framework  | Laravel 13.29.0 (locked in `composer.lock`)  |
| Admin      | Filament 5.7.6 (locked in `composer.lock`)   |
| SSO        | Keycloak, OIDC Authorization Code + PKCE     |
| RBAC       | spatie/laravel-permission + Filament Shield   |
| Audit log  | spatie/laravel-activitylog + filament-logger |
| Export     | Filament 5 native CSV export (7 exporters)    |
| Database   | PostgreSQL 16 (production/CI)                 |
| Cache/Queue| Redis 7 (production), database (local default) |
| PHP        | 8.4 (CI/container baseline; `composer.json` requires ^8.3) |
| Frontend   | Node.js 20.19+ / npm 10.8.2+ (CI uses Node 22) |
| CI         | GitHub Actions                                |

`composer.lock` and `package-lock.json` are required inputs to every
installation. CI uses `composer install` and `npm ci`, so dependency
installation cannot rewrite either lockfile.

## Local setup

Prerequisites: PHP 8.4, Composer 2, Node.js 20.19+, npm 10.8.2+.
PostgreSQL 16 and Redis 7 are the deployment baseline; the default local
environment uses SQLite and database-backed cache/queue.
```sh
cp .env.example .env
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan key:generate
php artisan migrate --seed
php artisan about
php artisan route:list
php artisan app:validate-environment   # after filling in KEYCLOAK_* values
php artisan serve                      # Filament panel at http://localhost:8000/admin
```

For a production-like local run, set `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=redis`, and
`CACHE_STORE=redis`, then provide the PostgreSQL and Redis connection variables from `.env`.
The `/up` liveness/readiness endpoint and `/health/ready` readiness alias report database,
cache, and queue readiness as `200`/`up` or `503`/`down`. Responses are `no-store` and never
return dependency exception details.

With Docker: `cp .env.example .env`, set `DB_PASSWORD`, then `docker compose up --build`
(PostgreSQL 16 + Redis 7 + app on port 8000).

## Environment variables

Required (fail fast via `php artisan app:validate-environment`):

| Variable              | Purpose                                            |
|-----------------------|----------------------------------------------------|
| `APP_KEY`             | Laravel app key (`php artisan key:generate`)       |
| `KEYCLOAK_BASE_URL`   | Keycloak origin, e.g. `https://sso.example.com`    |
| `KEYCLOAK_REALM`      | Keycloak realm, e.g. `umrah`                       |
| `KEYCLOAK_CLIENT_ID`  | OIDC client id                                     |
| `KEYCLOAK_CLIENT_SECRET` | OIDC confidential-client secret                   |
| `KEYCLOAK_REDIRECT_URI` | Callback URL, e.g. `https://app.example.com/auth/keycloak/callback` |
| `KEYCLOAK_POST_LOGOUT_REDIRECT_URI` | Allow-listed post-logout callback URL |

Optional:

| Variable              | Purpose                                            |
|-----------------------|----------------------------------------------------|
| `KEYCLOAK_ISSUER`     | ID-token `iss` override (default: `<BASE_URL>/realms/<REALM>`) |
| `KEYCLOAK_AUDIENCE`   | ID-token `aud` override (default: `KEYCLOAK_CLIENT_ID`)        |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | Logging channel / stack / level        |

The validator never prints secret values; it only names missing variables.

Production-like PostgreSQL/Redis deployments also set:

| Variable              | Purpose                                            |
|-----------------------|----------------------------------------------------|
| `DB_CONNECTION=pgsql` | PostgreSQL 16 connection driver                    |
| `DB_HOST` / `DB_PORT` | PostgreSQL host and port                           |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | PostgreSQL credentials |
| `REDIS_HOST` / `REDIS_PORT` | Redis 7 connection                  |
| `QUEUE_CONNECTION=redis` | Queue backend                                  |
| `CACHE_STORE=redis` | Cache backend                                      |

`DB_PASSWORD`, Redis credentials, `APP_KEY`, and `KEYCLOAK_CLIENT_SECRET` are
deployment secrets. Supply them through the environment or secret manager;
never commit them or place them in cached configuration.

## Keycloak OIDC (Authorization Code + PKCE)

Configure a Keycloak client with **Standard Flow**, **PKCE (S256)**, and the redirect URI from
`KEYCLOAK_REDIRECT_URI`.

- `GET /auth/keycloak/redirect` — starts the flow: session-bound `state` (64 chars) and PKCE S256
  `code_challenge`; the `code_verifier` never leaves the session.
- `GET /auth/keycloak/callback` — exchanges `code` + `code_verifier` at the token endpoint, validates
  the ID token's `iss` against `KEYCLOAK_ISSUER` (default `<BASE_URL>/realms/<REALM>`) and `aud`
  against `KEYCLOAK_AUDIENCE` (default `KEYCLOAK_CLIENT_ID`), fetches `userinfo`, and provisions the
  local user.
- Failed state checks, denied/absent authorization codes, and upstream errors surface as safe
  validation errors — no token material, client secrets, or raw upstream bodies are ever logged.
- `POST /logout` clears the local session and redirects to the Keycloak end-session endpoint.

### User provisioning & office assignment gate

Users are provisioned from the immutable Keycloak `sub`:

- A user is upserted by `keycloak_sub`; once set, the subject can never change (model-level guard +
  unique index).
- Panel access requires an **active office assignment**: an `office_user` row with `is_active = true`,
  in the `valid_from`–`valid_until` window, belonging to an office that is active and not disabled
  (`User::hasActiveAssignment()` drives `canAccessPanel()` and the login callback).
- Assignments default to the `Viewer` role (least privilege).
- The active office is session-pinned (`ActiveOfficeContext`, `POST /office/switch`); office-scoped
  models apply a global scope that fails closed when no office is active.

## Authorization model

- Roles: `Operasional`, `Pengadaan`, `Keuangan`, `Manager`, `Admin`, `Auditor`, `Viewer`
  (see `database/seeders/ProcurementRolesSeeder.php`).
- Permissions are namespaced: `procurement.view`, `procurement.create`, `procurement.update`,
  `procurement.delete`, `procurement.approve`, `procurement.export`, `procurement.manage-master-data`,
  `procurement.manage-finance`, `procurement.manage-users`, `procurement.manage-roles`.
- Filament Shield manages roles via the admin panel (`/admin/shield/roles`); the `super_admin` role
  bypasses the `RolePolicy` gate.
- Audit log at `/admin/activity-logs`; viewing requires `procurement.view`, export requires
  `procurement.export`.

## Logging

- Default `LOG_CHANNEL=stack` → `single` file (or `daily`). Production favours the `structured`
  channel: JSON lines on stderr with the `RedactSensitiveData` processor.
- `RedactSensitiveData` recursively redacts keys matching `token`, `secret`, `password`,
  `authorization`, `api_key`, `access_token`, `refresh_token`, `client_secret`, `cookie`, `code` —
  so credentials and OAuth material never reach the logs.
- The Keycloak controller logs only the exception class name on upstream failure, never the message.

## CI (GitHub Actions)

`.github/workflows/ci.yml` runs on every push/PR against PostgreSQL 16 and Redis 7:

1. `composer install --no-interaction --prefer-dist --no-progress` (locked dependencies)
2. `npm ci` and `npm run build` (locked frontend dependencies)
3. `composer validate --strict`
4. `php -l` on every tracked PHP file
5. `vendor/bin/pint --test` (style gate)
6. `php artisan route:list && php artisan about`
7. `php artisan config:cache && php artisan app:validate-environment` with dummy Keycloak env, then `config:clear`
8. `php artisan test --testsuite=Feature` against PostgreSQL 16 and Redis 7 (58 feature tests)

## Development

```sh
composer dev            # Laravel dev server + Vite hot reload
composer test           # run the test suite
vendor/bin/pint         # auto-format code
```

Tests use in-memory SQLite with array/sync test drivers (`phpunit.xml`); CI runs the same suite on
PostgreSQL 16 with Redis 7.
