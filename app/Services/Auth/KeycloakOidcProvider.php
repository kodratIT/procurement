<?php

namespace App\Services\Auth;

interface KeycloakOidcProvider
{
    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $verifier): array;

    /**
     * @return array<string, mixed>
     */
    public function userInfo(string $accessToken): array;

    public function logoutUrl(string $postLogoutRedirectUri): string;
}
