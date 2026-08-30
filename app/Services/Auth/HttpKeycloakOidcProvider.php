<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

class HttpKeycloakOidcProvider implements KeycloakOidcProvider
{
    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $verifier): array
    {
        return Http::asForm()->timeout(10)->post($this->endpoint('token'), array_filter([
            'grant_type' => 'authorization_code',
            'client_id' => config('keycloak.client_id'),
            'client_secret' => config('keycloak.client_secret'),
            'redirect_uri' => config('keycloak.redirect_uri'),
            'code' => $code,
            'code_verifier' => $verifier,
        ]))->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function userInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)->timeout(10)->get($this->endpoint('userinfo'))->throw()->json();
    }

    public function logoutUrl(string $postLogoutRedirectUri): string
    {
        return $this->endpoint('logout').'?'.http_build_query([
            'client_id' => config('keycloak.client_id'),
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ]);
    }

    private function endpoint(string $endpoint): string
    {
        return config('keycloak.base_url').'/realms/'.rawurlencode(config('keycloak.realm')).'/protocol/openid-connect/'.$endpoint;
    }
}
