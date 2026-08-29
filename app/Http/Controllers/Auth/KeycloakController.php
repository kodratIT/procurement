<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\KeycloakUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class KeycloakController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('keycloak.base_url') && config('keycloak.client_id'), 503, 'Keycloak is not configured.');

        $state = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $request->session()->put('keycloak.oauth', compact('state', 'verifier'));

        $query = http_build_query([
            'client_id' => config('keycloak.client_id'),
            'redirect_uri' => config('keycloak.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('keycloak.scopes')),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect(config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect/auth?'.$query);
    }

    public function callback(Request $request, KeycloakUserProvisioner $provisioner): RedirectResponse
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

        $base = config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect';

        try {
            $token = Http::asForm()->timeout(10)->post($base.'/token', array_filter([
                'grant_type' => 'authorization_code',
                'client_id' => config('keycloak.client_id'),
                'client_secret' => config('keycloak.client_secret'),
                'redirect_uri' => config('keycloak.redirect_uri'),
                'code' => $request->string('code')->toString(),
                'code_verifier' => $oauth['verifier'],
            ]))->throw()->json();
            abort_unless(is_string($token['access_token'] ?? null), 502, 'Keycloak returned an invalid token response.');
            $this->validateIdToken($token['id_token'] ?? null);
            $claims = Http::withToken($token['access_token'])->timeout(10)->get($base.'/userinfo')->throw()->json();
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

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect/logout?'.http_build_query([
            'client_id' => config('keycloak.client_id'),
            'post_logout_redirect_uri' => url('/'),
        ]));
    }

    private function validateIdToken(?string $idToken): void
    {
        if (! is_string($idToken) || $idToken === '') {
            throw ValidationException::withMessages(['oauth' => 'Keycloak did not return an ID token.']);
        }

        $parts = explode('.', $idToken);
        $claims = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        if (count($parts) !== 3 || ! is_array($claims)) {
            throw ValidationException::withMessages(['oauth' => 'Keycloak token validation failed.']);
        }

        $issuer = config('keycloak.issuer') ?: config('keycloak.base_url').'/realms/'.config('keycloak.realm');
        $audience = config('keycloak.audience') ?: config('keycloak.client_id');
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        if (($claims['iss'] ?? null) !== $issuer || ! in_array($audience, $audiences, true)) {
            throw ValidationException::withMessages(['oauth' => 'Keycloak token validation failed.']);
        }
    }
}
