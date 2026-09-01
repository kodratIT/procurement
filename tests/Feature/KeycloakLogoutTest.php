<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeycloakLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'keycloak.base_url' => 'https://keycloak.example.test',
            'keycloak.realm' => 'umrah',
            'keycloak.issuer' => 'https://keycloak.example.test/realms/umrah',
            'keycloak.audience' => 'procurement',
            'keycloak.client_id' => 'procurement',
            'keycloak.client_secret' => 'client-secret',
            'keycloak.redirect_uri' => 'https://procurement.example.test/auth/keycloak/callback',
            'keycloak.post_logout_redirect_uri' => 'https://procurement.example.test/',
            'keycloak.logout_redirect' => true,
        ]);
    }

    public function test_logout_invalidates_local_session_without_keycloak_redirect_when_disabled(): void
    {
        config(['keycloak.logout_redirect' => false]);
        $user = User::factory()->create(['keycloak_sub' => 'logout-subject']);

        $response = $this->actingAs($user)->withSession(['active_office_id' => 123])->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_unassigned_user_can_logout_through_keycloak_without_exposing_secrets(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'logout-subject']);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://keycloak.example.test/realms/umrah/protocol/openid-connect/logout?', $location);
        $this->assertStringNotContainsString('client-secret', $location);
        $this->assertStringNotContainsString('access_token', $location);
        $this->assertStringNotContainsString('refresh_token', $location);
        $this->assertGuest();
    }

    public function test_logout_falls_back_to_local_redirect_when_keycloak_is_not_configured(): void
    {
        config([
            'keycloak.base_url' => '',
            'keycloak.client_secret' => null,
        ]);
        $user = User::factory()->create(['keycloak_sub' => 'logout-subject']);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
