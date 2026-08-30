<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeycloakOidcNonceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'keycloak.base_url' => 'https://keycloak.example.test',
            'keycloak.realm' => 'umrah',
            'keycloak.client_id' => 'procurement',
            'keycloak.redirect_uri' => 'https://procurement.example.test/auth/keycloak/callback',
        ]);
    }

    public function test_redirect_binds_a_nonce_to_the_authorization_request(): void
    {
        $response = $this->get(route('keycloak.redirect'));

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame(64, strlen((string) ($query['nonce'] ?? '')));
        $this->assertSame($query['nonce'], $this->app['session']->get('keycloak.oauth.nonce'));
    }
}
