<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\KeycloakClient;
use App\Services\Auth\KeycloakUserProvisioner;
use App\Support\KeycloakConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class KeycloakController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        try {
            $config = KeycloakConfig::fromConfig();
        } catch (InvalidArgumentException) {
            abort(503, 'Keycloak is not configured.');
        }

        $state = Str::random(64);
        $nonce = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $request->session()->put('keycloak.oauth', [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'redirect_uri' => $config->redirectUri,
        ]);

        $client = new KeycloakClient($config);

        return redirect()->away($client->authorizationUrl($state, $nonce, $challenge));
    }

    public function callback(Request $request, KeycloakUserProvisioner $provisioner): RedirectResponse
    {
        try {
            $config = KeycloakConfig::fromConfig();
        } catch (InvalidArgumentException) {
            abort(503, 'Keycloak is not configured.');
        }

        $oauth = $request->session()->pull('keycloak.oauth');
        $callbackState = $request->query('state');
        $code = $request->query('code');
        if (! is_array($oauth)
            || ! is_string($oauth['state'] ?? null)
            || ! is_string($oauth['nonce'] ?? null)
            || ! is_string($oauth['verifier'] ?? null)
            || ! is_string($oauth['redirect_uri'] ?? null)
            || ! is_string($callbackState)
            || ! hash_equals($oauth['state'], $callbackState)
            || ! hash_equals($oauth['redirect_uri'], $config->redirectUri)) {
            throw ValidationException::withMessages(['oauth' => 'Invalid OAuth state. Please try signing in again.']);
        }
        if ($request->has('error')) {
            throw ValidationException::withMessages(['oauth' => 'Keycloak denied the sign-in request.']);
        }
        if (! is_string($code) || $code === '') {
            throw ValidationException::withMessages(['oauth' => 'Authorization code is missing.']);
        }

        $client = new KeycloakClient($config);

        try {
            $token = $client->exchangeCode(
                $code,
                $oauth['verifier'],
            );
            $idTokenClaims = $client->validateIdToken($token['id_token'], $oauth['nonce']);
            $claims = $client->userInfo($token['access_token']);
            if (($claims['sub'] ?? null) !== ($idTokenClaims['sub'] ?? null)) {
                throw new RuntimeException('Keycloak subject mismatch.');
            }
        } catch (Throwable $exception) {
            Log::warning('Keycloak OIDC sign-in failed.', [
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages(['oauth' => 'Sign-in failed. Please try again.']);
        }
        $user = $provisioner->provision($claims);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! config('keycloak.logout_redirect', true)) {
            return redirect('/');
        }

        try {
            $config = KeycloakConfig::fromConfig();
        } catch (InvalidArgumentException) {
            return redirect('/');
        }

        return redirect()->away($config->issuer.'/protocol/openid-connect/logout?'.http_build_query([
            'client_id' => $config->clientId,
            'post_logout_redirect_uri' => $config->postLogoutRedirectUri,
        ], '', '&', PHP_QUERY_RFC3986));
    }
}
