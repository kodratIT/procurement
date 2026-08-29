<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KeycloakController extends Controller
{
    public function redirect(Request $request)
    {
        $state = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $request->session()->put('keycloak.oauth', compact('state', 'verifier'));
        $query = http_build_query([
            'client_id' => config('keycloak.client_id'), 'redirect_uri' => config('keycloak.redirect_uri'),
            'response_type' => 'code', 'scope' => implode(' ', config('keycloak.scopes')), 'state' => $state,
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]);

        return redirect(config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect/auth?'.$query);
    }

    public function callback(Request $request)
    {
        $oauth = $request->session()->pull('keycloak.oauth');
        abort_unless($oauth && hash_equals($oauth['state'], (string) $request->string('state')), 419, 'Invalid OAuth state.');
        abort_unless($request->filled('code'), 422, 'Authorization code is missing.');
        $base = config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect';
        $token = Http::asForm()->timeout(10)->post($base.'/token', array_filter([
            'grant_type' => 'authorization_code', 'client_id' => config('keycloak.client_id'),
            'client_secret' => config('keycloak.client_secret'), 'redirect_uri' => config('keycloak.redirect_uri'),
            'code' => $request->string('code')->toString(), 'code_verifier' => $oauth['verifier'],
        ]))->throw()->json();
        $claims = Http::withToken($token['access_token'])->timeout(10)->get($base.'/userinfo')->throw()->json();
        $sub = (string) ($claims['sub'] ?? '');
        abort_unless($sub !== '', 422, 'Keycloak subject is missing.');
        $user = User::updateOrCreate(['keycloak_sub' => $sub], [
            'name' => $claims['name'] ?? $claims['preferred_username'] ?? $sub,
            'email' => $claims['email'] ?? null, 'email_verified_at' => now(),
        ]);
        if (! $user->offices()->exists()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account has no office assignment. Contact an administrator.']);
        }
        Auth::login($user, remember: false);

        return redirect()->intended('/admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
