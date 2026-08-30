# Umrah Procurement foundation

Laravel 13 + Filament 5 baseline for the umrah travel procurement platform.

## Local setup

1. Copy `.env.example` to `.env` and set `APP_KEY`, PostgreSQL, Redis, and Keycloak values.
2. Run `composer install`.
3. Run `php artisan app:validate-environment`.
4. Run `php artisan migrate`.
5. Configure a Keycloak client with Standard Flow, PKCE S256, and the redirect URI in `KEYCLOAK_REDIRECT_URI`.
6. Start with `php artisan serve`; the Filament panel is at `/admin`.

## Keycloak OIDC (Authorization Code + PKCE)

- `/auth/keycloak/redirect` starts the flow: a session-bound random `state` and a PKCE `S256` `code_challenge` are generated; the `code_verifier` never leaves the session.
- `/auth/keycloak/callback` exchanges the authorization `code` with the `code_verifier`, validates the ID token's `iss` against `KEYCLOAK_ISSUER` (defaults to `KEYCLOAK_BASE_URL` + `/realms/` + `KEYCLOAK_REALM`) and `aud` against `KEYCLOAK_AUDIENCE` (defaults to `KEYCLOAK_CLIENT_ID`), then fetches `userinfo` to provision the local user.
- Failed state checks, denied/absent authorization codes, and upstream errors are surfaced as safe validation errors — no token material, client secrets, or raw upstream bodies are ever logged.
- `POST /logout` clears the local session and redirects to the Keycloak end-session endpoint.

Users are provisioned from immutable Keycloak `sub`. A user needs an `office_user` assignment before panel access. OAuth state and PKCE verifier are session-bound; secrets and access tokens are not logged.

## Docker

Set `DB_PASSWORD` in `.env`, then run `docker compose up --build`. PostgreSQL and Redis use named persistent volumes.

## CI

GitHub Actions runs a PHP 8.3/8.4 matrix against PostgreSQL 16. Each job runs:

1. `composer validate --strict --no-check-publish`
2. PHP syntax checks and `vendor/bin/pint --test`
3. `php artisan app:validate-environment` with non-secret placeholder values
4. `php artisan test` against the PostgreSQL service

Run the same checks locally before opening a pull request:

```sh
composer validate --strict --no-check-publish
git ls-files '*.php' -z | xargs -0 -n1 php -l
vendor/bin/pint --test
php artisan app:validate-environment
php artisan test
```

## Filament development

The admin panel is registered through Filament Panel Builder at `/admin`. After installing
dependencies, clear cached configuration and run the frontend build when panel assets change:

```sh
php artisan filament:upgrade
php artisan optimize:clear
npm install
npm run build
```

Resource and panel authorization is enforced by the assignment gate and Filament Shield permissions;
do not bypass these checks when adding a resource or custom page.

## Contributor workflow

Use one task per branch, named `feat/f<backlog-id>-<short-description>` (for example,
`feat/f1.7-ci-logging-docs`). Keep commits small and conventional (`feat:`, `fix:`, `test:`, `docs:`,
or `chore:`), run the CI-equivalent commands above, and open a pull request into `main`.

Never commit `.env`, credentials, client secrets, access tokens, or production configuration.

## Troubleshooting

- **Environment validation fails:** copy `.env.example` to `.env`, generate `APP_KEY`, fill the
  required Keycloak values, and rerun `php artisan app:validate-environment`. Error output names only
  variable names, never their values.
- **Database connection fails in Docker:** set `DB_PASSWORD` in `.env`, then run
  `docker compose down` followed by `docker compose up --build`.
- **Keycloak callback fails:** verify the client uses Standard Flow and PKCE S256, and that the exact
  `KEYCLOAK_REDIRECT_URI` is registered in Keycloak.
- **Panel access is forbidden:** the signed-in user needs an active `office_user` assignment within
  its validity window and an active office.
- **Stale Filament assets:** run `php artisan optimize:clear`, then `npm run build`.
