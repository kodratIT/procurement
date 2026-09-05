# Admin Login Page Design

## Problem

`AdminPanelProvider` registers `KeycloakController::redirect` as Filament's login route action. A request to `/admin/login` therefore skips the local Filament login page and immediately returns a 302 response to Keycloak.

## Decision

Render a local Filament login page first, with one SSO action that continues to the existing Keycloak flow. Do not add a local email/password path because application authentication is provisioned through Keycloak.

## Architecture

- Add `App\\Filament\\Pages\\Auth\\Login` extending `Filament\\Auth\\Pages\\Login`.
- Preserve Filament's normal authenticated-user redirect for allowed users; render a logout-only state for authenticated users denied by assignment.
- Override the credential form content with a full-width Filament action labeled `Masuk dengan Keycloak` and linked to the named `keycloak.redirect` route.
- Register the page with `AdminPanelProvider::panel()` via `->login(Login::class)`.
- Leave `KeycloakController`, PKCE state handling, callback validation, and user provisioning unchanged.

## Request Flow

1. Unauthenticated `GET /admin` is handled by Filament's auth middleware.
2. Filament redirects to `/admin/login`.
3. `/admin/login` returns the local Livewire/Filament page with the SSO button.
4. Clicking the button requests `/auth/keycloak/redirect`.
5. The existing controller stores state, nonce, and PKCE verifier, then redirects to Keycloak.
6. Keycloak returns to the existing callback, which validates the response and signs the user into the local session.

## Error Handling

- Missing Keycloak configuration remains a 503 from the existing redirect controller after the SSO action is selected.
- OAuth state, nonce, token, subject, and provisioning failures remain governed by the existing callback behavior.
- Allowed authenticated users continue to be redirected by Filament; blocked authenticated users see the local page with a logout action.

## Verification

- Add a feature test that requests the Filament login route and confirms a successful local response containing the SSO action and the `keycloak.redirect` URL.
- Run the focused login feature tests.
- Use the local browser to confirm `/admin` reaches the Laravel login page first and that the SSO action reaches the Keycloak sign-in page.
