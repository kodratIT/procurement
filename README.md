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

The local stack runs the app, PostgreSQL 16, and Redis 7 via `compose.yaml`:

1. Copy `.env.example` to `.env` and set `APP_KEY` (`php artisan key:generate`) and `DB_PASSWORD`.
2. Run `docker compose up --build` (or `docker compose up -d postgres redis` for just the data services).
3. The app container runs `migrate --force` and `db:seed --force` on start, then serves on http://localhost:8000.

PostgreSQL and Redis use named persistent volumes (`postgres-data`, `redis-data`), both expose healthchecks, and the app waits for them via `depends_on: condition: service_healthy`. For local (non-Docker) development, point `DB_HOST`/`REDIS_HOST` at `127.0.0.1`; the services are published on ports 5432 and 6379.

## CI

GitHub Actions runs Pint and the Laravel test suite against PostgreSQL.
