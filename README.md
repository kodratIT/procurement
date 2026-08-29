# Umrah Procurement foundation

Laravel 13 + Filament 5 baseline for the umrah travel procurement platform.

## Requirements

- PHP 8.3+ with `pdo_pgsql`, Composer 2, and Node.js 20+
- PostgreSQL 16 and Redis 7, or Docker Compose
- A Keycloak realm and client configured for OIDC

## Local setup

1. Copy `.env.example` to `.env`.
2. Install dependencies: `composer install`.
3. Generate an application key: `php artisan key:generate`.
4. Set PostgreSQL, Redis, and Keycloak values in `.env`.
5. Validate configuration: `php artisan app:validate-environment`.
6. Run migrations: `php artisan migrate`.
7. (Optional) Build assets: `npm install && npm run build`.
8. Configure a Keycloak client with Standard Flow, PKCE S256, and the redirect URI in `KEYCLOAK_REDIRECT_URI`.
9. Start with `php artisan serve`; the Filament panel is at `/admin`.

Users are provisioned from immutable Keycloak `sub`. A user needs an `office_user` assignment before panel access. OAuth state and PKCE verifier are session-bound; secrets and access tokens are not logged.

## Docker

Set `DB_PASSWORD` in `.env`, then run `docker compose up --build`. PostgreSQL and Redis use named persistent volumes.

## CI

GitHub Actions runs the quality checks and Laravel test suite against PostgreSQL.

## Logging and security

The `structured` log stack writes newline-delimited JSON to stderr for container collectors. Context and extra fields are recursively redacted for common credential keys such as tokens, passwords, secrets, authorization values, and OAuth codes. Never put credentials in exception messages or log context.

For local human-readable file logs, set `LOG_STACK=single` and `LOG_LEVEL=debug`.

## Quality checks

    composer validate --strict --no-check-publish
    git ls-files '*.php' -z | xargs -0 -n1 php -l
    vendor/bin/pint --test
    php artisan app:validate-environment
    php artisan test

GitHub Actions runs these checks on every push and pull request against PostgreSQL 16.
