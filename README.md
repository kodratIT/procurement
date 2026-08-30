# Umrah Procurement

Laravel 13 + Filament 5 procurement platform for umrah travel operators. Multi-office RBAC with
Keycloak SSO (OIDC Authorization Code + PKCE), role/permission-based authorization, audit logging,
and CSV export — all free/open-source plugins.

## Tech stack

| Layer      | Choice                                        |
|------------|-----------------------------------------------|
| Framework  | Laravel 13 (PHP 8.4)                          |
| Admin      | Filament 5 Panel Builder                       |
| SSO        | Keycloak, OIDC Authorization Code + PKCE S256 |
| RBAC       | spatie/laravel-permission + Filament Shield   |
| Audit log  | spatie/laravel-activitylog + filament-logger  |
| Export     | Filament 5 native CSV export (7 exporters)    |
| Database   | PostgreSQL 16 (prod/CI), SQLite (tests)       |
| Cache/Queue| Redis 7 / database                            |
| CI         | GitHub Actions                                |

## Local setup

Prerequisites: PHP 8.3+, Composer 2, Node 20+.

```sh
cp .env.example .env        # then fill in APP_KEY, DB_PASSWORD, KEYCLOAK_*
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate --seed
php artisan app:validate-environment   # fails fast if required env is missing
php artisan serve                      # Filament panel at http://localhost:8000/admin
```

The default local configuration uses PostgreSQL, Redis for cache/queue/session, and the
private local filesystem at `storage/app/private`. The application timezone is `Asia/Jakarta`.
Mail defaults to the `log` mailer; replace the `MAIL_*` placeholders only when an SMTP service
is available. Never commit `.env` or real credentials.

### Laravel and Filament foundation installation

The foundation uses Laravel 13 and the official Filament 5 Panel Builder. The packages and
exactly-resolved versions are committed in `composer.lock`. To reproduce the installation in a
fresh checkout, run:

```sh
composer install
php artisan filament:install --panels
```

The installer creates `app/Providers/Filament/AdminPanelProvider.php` and registers it in
`bootstrap/providers.php`. This repository keeps that provider as the `admin` panel at `/admin`,
with login enabled and the existing office-scoped resources and authorization plugins preserved.
Verify the panel foundation with:

```sh
php artisan about
php artisan test --filter=FoundationTest
```

With Docker, set `DB_PASSWORD` in `.env` and run `docker compose up --build` (PostgreSQL 16,
Redis 7, and the app on port 8000). The database and Redis data are retained in named volumes.
Use `docker compose down -v` only when intentionally deleting local data.

## Environment variables

Required (fail fast via `php artisan app:validate-environment`):

| Variable              | Purpose                                            |
|-----------------------|----------------------------------------------------|
| `APP_KEY`             | Laravel app key (`php artisan key:generate`)       |
| `KEYCLOAK_BASE_URL`   | Keycloak origin, e.g. `https://sso.example.com`    |
| `KEYCLOAK_REALM`      | Keycloak realm, e.g. `umrah`                       |
| `KEYCLOAK_CLIENT_ID`  | OIDC client id                                     |
| `KEYCLOAK_REDIRECT_URI` | Callback URL, e.g. `https://app.example.com/auth/keycloak/callback` |

Optional:

| Variable              | Purpose                                            |
|-----------------------|----------------------------------------------------|
| `KEYCLOAK_ISSUER`     | ID-token `iss` override (default: `<BASE_URL>/realms/<REALM>`) |
| `KEYCLOAK_AUDIENCE`   | ID-token `aud` override (default: `KEYCLOAK_CLIENT_ID`)        |
| `KEYCLOAK_CLIENT_SECRET` | Confidential-client secret (omit for public client)        |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | Logging channel / stack / level        |

The validator never prints secret values; it only names missing variables.

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

`.github/workflows/ci.yml` runs on every push/PR against PostgreSQL 16:

1. `composer validate --strict --no-check-publish`
2. `php -l` on every tracked PHP file
3. `vendor/bin/pint --test` (style gate)
4. `php artisan app:validate-environment` (with dummy Keycloak env)
5. `php artisan test` against the PostgreSQL service (33 feature/unit tests)

## Development

```sh
composer dev            # Laravel dev server + Vite hot reload
composer test           # run the test suite
vendor/bin/pint         # auto-format code
```

Tests use in-memory SQLite (`phpunit.xml`); CI runs the same suite on PostgreSQL.
