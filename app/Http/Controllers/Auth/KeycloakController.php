<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\KeycloakOidcProvider;
use App\Services\Auth\KeycloakUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class KeycloakController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->configurationIsSafe(), 503, 'Keycloak is not configured.');

        $state = Str::random(64);
        $nonce = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $request->session()->put('keycloak.oauth', compact('state', 'nonce', 'verifier'));

        $query = http_build_query([
            'client_id' => config('keycloak.client_id'),
            'redirect_uri' => config('keycloak.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('keycloak.scopes')),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect(config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect/auth?'.$query);
    }

    public function callback(Request $request, KeycloakOidcProvider $provider, KeycloakUserProvisioner $provisioner): RedirectResponse
    {
        $oauth = $request->session()->pull('keycloak.oauth');
        if (! $oauth || ! hash_equals((string) ($oauth['state'] ?? ''), (string) $request->string('state'))) {
            throw ValidationException::withMessages(['oauth' => 'Invalid OAuth state. Please try signing in again.']);
        }
        if ($request->filled('error')) {
            throw ValidationException::withMessages(['oauth' => 'Keycloak denied the sign-in request.']);
        }
        if (! $request->filled('code')) {
            throw ValidationException::withMessages(['oauth' => 'Authorization code is missing.']);
        }

        try {
            $token = $provider->exchangeCode($request->string('code')->toString(), (string) $oauth['verifier']);
            abort_unless(is_string($token['access_token'] ?? null), 502, 'Keycloak returned an invalid token response.');
            $this->validateIdToken($token['id_token'] ?? null, (string) ($oauth['nonce'] ?? ''));
            $claims = $provider->userInfo($token['access_token']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // The upstream exception may carry the raw token endpoint response
            // (including access/refresh tokens) in its message, so never report
            // it verbatim — log a sanitized summary instead.
            Log::warning('Keycloak OIDC sign-in failed.', [
                'exception' => $exception::class,
            ]);
            throw ValidationException::withMessages(['oauth' => 'Sign-in failed. Please try again.']);
        }

        $user = $provisioner->provision($claims);
        if (! $user->hasActiveAssignment()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account has no active office assignment. Contact an administrator.']);
        }
        Auth::login($user, remember: false);

        return redirect()->intended('/admin');
    }

    public function logout(Request $request, KeycloakOidcProvider $provider): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort_unless($this->configurationIsSafe(includeLogout: true), 503, 'Keycloak is not configured.');

        return redirect($provider->logoutUrl(config('keycloak.post_logout_redirect_uri') ?: url('/')));
    }

    private function configurationIsSafe(bool $includeLogout = false): bool
    {
        $uris = [config('keycloak.base_url'), config('keycloak.redirect_uri')];
        if ($includeLogout) {
            $uris[] = config('keycloak.post_logout_redirect_uri');
        }
        foreach ($uris as $uri) {
            $parts = is_string($uri) ? parse_url($uri) : false;
            if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
                || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['query']) || isset($parts['fragment'])) {
                return false;
            }
        }

        return (bool) config('keycloak.client_id') && (bool) config('keycloak.realm');
    }

    private function validateIdToken(?string $idToken, string $expectedNonce): void
    {
        if (! is_string($idToken) || $idToken === '') {
            throw ValidationException::withMessages(['oauth' => 'Keycloak did not return an ID token.']);
        }

        $parts = explode('.', $idToken);
        $header = json_decode(base64_decode(strtr($parts[0] ?? '', '-_', '+/')), true);
        $claims = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        if (count($parts) !== 3 || ! is_array($header) || ! is_array($claims)
            || ($header['alg'] ?? null) !== 'RS256' || ($parts[2] ?? '') === '') {
            throw ValidationException::withMessages(['oauth' => 'Keycloak token validation failed.']);
        }

        $issuer = config('keycloak.issuer') ?: config('keycloak.base_url').'/realms/'.config('keycloak.realm');
        $audience = config('keycloak.audience') ?: config('keycloak.client_id');
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        $now = now()->timestamp;
        if (($claims['iss'] ?? null) !== $issuer
            || ! in_array($audience, $audiences, true)
            || $expectedNonce === ''
            || ! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))
            || ! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < $now
            || ! is_numeric($claims['iat'] ?? null) || (int) $claims['iat'] > $now + 60
            || (count($audiences) > 1 && ($claims['azp'] ?? null) !== config('keycloak.client_id'))
            || (isset($claims['nbf']) && (! is_numeric($claims['nbf']) || (int) $claims['nbf'] > $now))) {
            throw ValidationException::withMessages(['oauth' => 'Keycloak token validation failed.']);
        }
    }
}
