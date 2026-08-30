<?php

return [
    'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL', ''), '/'),
    'realm' => env('KEYCLOAK_REALM', 'master'),
    'issuer' => env('KEYCLOAK_ISSUER'),
    'audience' => env('KEYCLOAK_AUDIENCE', env('KEYCLOAK_CLIENT_ID', '')),
    'client_id' => env('KEYCLOAK_CLIENT_ID', ''),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI', env('APP_URL').'/auth/keycloak/callback'),
    'post_logout_redirect_uri' => env('KEYCLOAK_POST_LOGOUT_REDIRECT_URI', env('APP_URL').'/'),
    'scopes' => ['openid', 'profile', 'email'],
    'pkce' => true,
];
