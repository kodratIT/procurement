<?php

return [
    'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL', ''), '/'),
    'realm' => env('KEYCLOAK_REALM'),
    'issuer' => env('KEYCLOAK_ISSUER'),
    'audience' => env('KEYCLOAK_AUDIENCE', env('KEYCLOAK_CLIENT_ID', '')),
    'client_id' => env('KEYCLOAK_CLIENT_ID', ''),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
    'post_logout_redirect_uri' => env('KEYCLOAK_POST_LOGOUT_REDIRECT_URI'),
    'authorization_endpoint' => env('KEYCLOAK_AUTHORIZATION_ENDPOINT'),
    'token_endpoint' => env('KEYCLOAK_TOKEN_ENDPOINT'),
    'userinfo_endpoint' => env('KEYCLOAK_USERINFO_ENDPOINT'),
    'jwks_uri' => env('KEYCLOAK_JWKS_URI'),
    'scopes' => ['openid', 'profile', 'email'],
    'pkce' => true,
    'clock_skew' => (int) env('KEYCLOAK_CLOCK_SKEW', 60),
    'provisioning_mode' => env('KEYCLOAK_PROVISIONING_MODE', 'hybrid'),
    'logout_redirect' => filter_var(env('KEYCLOAK_LOGOUT_REDIRECT', true), FILTER_VALIDATE_BOOL),
];
