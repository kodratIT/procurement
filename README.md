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

GitHub Actions runs Pint and the Laravel test suite against PostgreSQL.
