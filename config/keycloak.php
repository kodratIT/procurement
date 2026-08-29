<?php

return [
    'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL', ''), '/'),
    'realm' => env('KEYCLOAK_REALM', 'master'),
    'client_id' => env('KEYCLOAK_CLIENT_ID', ''),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI', env('APP_URL').'/auth/keycloak/callback'),
    'scopes' => ['openid', 'profile', 'email'],
    'pkce' => true,
];
