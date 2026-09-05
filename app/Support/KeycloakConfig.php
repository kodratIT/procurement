<?php

namespace App\Support;

use InvalidArgumentException;

class KeycloakConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $realm,
        public readonly string $issuer,
        public readonly string $audience,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $postLogoutRedirectUri,
        public readonly array $scopes,
        public readonly ?string $authorizationEndpoint,
        public readonly ?string $tokenEndpoint,
        public readonly ?string $userinfoEndpoint,
        public readonly ?string $jwksUri,
    ) {}

    public static function fromConfig(): self
    {
        $baseUrl = (string) config('keycloak.base_url', '');
        $clientId = (string) config('keycloak.client_id', '');
        $clientSecret = (string) config('keycloak.client_secret', '');
        $redirectUri = (string) config('keycloak.redirect_uri', '');

        if ($baseUrl === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new InvalidArgumentException('Keycloak is not configured.');
        }

        $realm = (string) config('keycloak.realm', '');

        return new self(
            baseUrl: $baseUrl,
            realm: $realm,
            issuer: (string) (config('keycloak.issuer') ?: $baseUrl.'/realms/'.rawurlencode($realm)),
            audience: (string) (config('keycloak.audience') ?: $clientId),
            clientId: $clientId,
            clientSecret: $clientSecret,
            redirectUri: $redirectUri,
            postLogoutRedirectUri: (string) config('keycloak.post_logout_redirect_uri', ''),
            scopes: (array) config('keycloak.scopes', ['openid', 'profile', 'email']),
            authorizationEndpoint: self::nullableString(config('keycloak.authorization_endpoint')),
            tokenEndpoint: self::nullableString(config('keycloak.token_endpoint')),
            userinfoEndpoint: self::nullableString(config('keycloak.userinfo_endpoint')),
            jwksUri: self::nullableString(config('keycloak.jwks_uri')),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
