<?php

namespace App\Services\Auth;

use App\Support\KeycloakConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakClient
{
    public function __construct(private readonly KeycloakConfig $config) {}

    public function authorizationUrl(string $state, string $nonce, string $challenge): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => implode(' ', $this->config->scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return $this->endpoint('auth').'?'.$query;
    }

    public function exchangeCode(string $code, string $verifier): array
    {
        $token = Http::asForm()->timeout(10)->post($this->endpoint('token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'redirect_uri' => $this->config->redirectUri,
            'code' => $code,
            'code_verifier' => $verifier,
        ])->throw()->json();

        if (! is_string($token['access_token'] ?? null)) {
            throw new RuntimeException('Keycloak returned an invalid token response.');
        }

        return $token;
    }

    public function validateIdToken(?string $idToken, string $expectedNonce): array
    {
        if (! is_string($idToken) || $idToken === '') {
            throw new RuntimeException('Keycloak did not return an ID token.');
        }

        $parts = explode('.', $idToken);
        $claims = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);

        if (count($parts) !== 3 || ! is_array($claims)) {
            throw new RuntimeException('Keycloak token validation failed.');
        }

        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        if (($claims['iss'] ?? null) !== $this->config->issuer
            || ! in_array($this->config->audience, $audiences, true)
            || $expectedNonce === ''
            || ! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Keycloak token validation failed.');
        }

        return $claims;
    }

    public function userInfo(string $accessToken): array
    {
        $claims = Http::withToken($accessToken)->timeout(10)->get($this->endpoint('userinfo'))->throw()->json();

        return is_array($claims) ? $claims : [];
    }

    public function logoutUrl(?string $idTokenHint = null, ?string $postLogoutRedirectUri = null): string
    {
        $params = [
            'client_id' => $this->config->clientId,
        ];
        if (is_string($idTokenHint) && $idTokenHint !== '') {
            $params['id_token_hint'] = $idTokenHint;
        }
        if (is_string($postLogoutRedirectUri) && $postLogoutRedirectUri !== '') {
            $params['post_logout_redirect_uri'] = $postLogoutRedirectUri;
        }

        return $this->endpoint('logout').'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function revokeToken(string $token, string $hint = 'refresh_token'): void
    {
        Http::asForm()->timeout(10)->post($this->endpoint('revoke') ?: $this->config->baseUrl.'/realms/'.rawurlencode($this->config->realm).'/protocol/openid-connect/revoke', [
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'token' => $token,
            'token_type_hint' => $hint,
        ])->throw();
    }

    public function backchannelLogout(string $refreshToken): void
    {
        Http::asForm()->timeout(10)->post($this->endpoint('logout'), [
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'refresh_token' => $refreshToken,
        ])->throw();
    }

    private function endpoint(string $action): string
    {
        $override = match ($action) {
            'auth' => $this->config->authorizationEndpoint,
            'token' => $this->config->tokenEndpoint,
            'userinfo' => $this->config->userinfoEndpoint,
            'logout' => null,
            'revoke' => null,
        };

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->config->baseUrl.'/realms/'.rawurlencode($this->config->realm).'/protocol/openid-connect/'.$action;
    }
}
