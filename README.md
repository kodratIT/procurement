# Umrah Procurement foundation

Laravel 13 + Filament 5 baseline for the umrah travel procurement platform.

## Local setup

1. Copy `.env.example` to `.env` and set `APP_KEY`, PostgreSQL, Redis, and Keycloak values.
2. Run `composer install`.
3. Run `php artisan app:validate-environment`.
4. Run `php artisan migrate`.
5. Configure a Keycloak client with Standard Flow, PKCE S256, and the redirect URI in `KEYCLOAK_REDIRECT_URI`.
6. Start with `php artisan serve`; the Filament panel is at `/admin`.

Users are provisioned from immutable Keycloak `sub`. A user needs an `office_user` assignment before panel access. OAuth state and PKCE verifier are session-bound; secrets and access tokens are not logged.

## Docker

Set `DB_PASSWORD` in `.env`, then run `docker compose up --build`. PostgreSQL and Redis use named persistent volumes.

## CI

GitHub Actions runs Pint and the Laravel test suite against PostgreSQL.
