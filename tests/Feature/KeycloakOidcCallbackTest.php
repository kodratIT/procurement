<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeycloakOidcCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'https://keycloak.example.test/realms/umrah';

    private const CLIENT_ID = 'procurement';

    private const BASE_URL = 'https://keycloak.example.test';

    private const REALM = 'umrah';

    private const REDIRECT_URI = 'https://procurement.example.test/auth/keycloak/callback';

    private const POST_LOGOUT_REDIRECT_URI = 'https://procurement.example.test/';

    private const NONCE = 'test-nonce-value';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'keycloak.base_url' => self::BASE_URL,
            'keycloak.realm' => self::REALM,
            'keycloak.issuer' => self::ISSUER,
            'keycloak.audience' => self::CLIENT_ID,
            'keycloak.client_id' => self::CLIENT_ID,
            'keycloak.client_secret' => 'client-secret',
            'keycloak.redirect_uri' => self::REDIRECT_URI,
            'keycloak.post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI,
        ]);
    }

    public function test_redirect_starts_pkce_flow_with_state_and_s256_challenge(): void
    {
        $response = $this->get(route('keycloak.redirect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/auth?', $location);

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(self::REDIRECT_URI, $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(64, strlen((string) $query['state']));
        $this->assertSame(43, strlen((string) $query['code_challenge']));

        $oauth = $this->app['session']->get('keycloak.oauth');
        $this->assertSame($query['state'], $oauth['state']);
        $this->assertTrue(hash_equals($query['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', $oauth['verifier'], true)), '+/', '-_'), '=')));
    }

    public function test_callback_without_session_state_is_rejected_safely(): void
    {
        $response = $this->get(route('keycloak.callback', ['state' => 'attacker-state', 'code' => 'attacker-code']));

        $response->assertSessionHasErrors('oauth');
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_callback_requires_code_and_never_exchanges_without_verifier(): void
    {
        Http::fake();

        $state = 'valid-state';
        $this->withSession(['keycloak.oauth' => $this->oauthSession($state)]);
        $response = $this->get(route('keycloak.callback', ['state' => $state]));

        $response->assertSessionHasErrors('oauth');
        Http::assertNothingSent();
    }

    public function test_callback_exchanges_code_with_pkce_verifier_and_verifies_issuer_and_audience(): void
    {
        $office = Office::factory()->create(['code' => 'JKT', 'name' => 'Jakarta']);
        $existing = User::factory()->create(['keycloak_sub' => 'immutable-sub-1', 'email' => 'budi@example.test']);
        UserAssignment::factory()->create([
            'user_id' => $existing->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $idToken = $this->idToken(['iss' => self::ISSUER, 'aud' => self::CLIENT_ID, 'nonce' => self::NONCE, 'sub' => 'immutable-sub-1']);
        $state = 'valid-state';
        Http::fake([
            self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access-token',
                'id_token' => $idToken,
            ]),
            self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'immutable-sub-1',
                'name' => 'Budi Santoso',
                'email' => 'budi@example.test',
            ]),
        ]);

        $this->withSession(['keycloak.oauth' => $this->oauthSession($state)]);
        $response = $this->get(route('keycloak.callback', ['state' => $state, 'code' => 'exchange-me']));

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();

        $user = User::where('keycloak_sub', 'immutable-sub-1')->first();
        $this->assertNotNull($user);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame('budi@example.test', $user->email);
        $this->assertTrue($user->assignments()->where('office_id', $office->id)->exists());
        $this->assertTrue($user->hasActiveAssignment());

        Http::assertSent(function ($request) {
            if (! str_ends_with((string) $request->url(), '/token')) {
                return false;
            }

            return $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'exchange-me'
                && $request['code_verifier'] === 'valid-verifier'
                && $request['client_id'] === self::CLIENT_ID
                && $request['redirect_uri'] === self::REDIRECT_URI;
        });
    }

    public function test_callback_rejects_token_with_wrong_issuer(): void
    {
        $state = 'valid-state';
        Http::fake([
            self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access-token',
                'id_token' => $this->idToken(['iss' => 'https://evil.example.test/realms/umrah', 'aud' => self::CLIENT_ID, 'nonce' => self::NONCE]),
            ]),
        ]);

        $this->withSession(['keycloak.oauth' => $this->oauthSession($state)]);
        $response = $this->get(route('keycloak.callback', ['state' => $state, 'code' => 'exchange-me']));

        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
        Http::assertNotSent(fn ($request) => str_ends_with((string) $request->url(), '/userinfo'));
    }

    public function test_callback_rejects_token_with_wrong_audience(): void
    {
        $state = 'valid-state';
        Http::fake([
            self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access-token',
                'id_token' => $this->idToken(['iss' => self::ISSUER, 'aud' => 'another-client', 'nonce' => self::NONCE]),
            ]),
        ]);

        $this->withSession(['keycloak.oauth' => $this->oauthSession($state)]);
        $response = $this->get(route('keycloak.callback', ['state' => $state, 'code' => 'exchange-me']));

        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
    }

    public function test_callback_error_response_is_reported_safely_without_token_in_logs(): void
    {
        $state = 'valid-state';
        Http::fake([
            self::BASE_URL.'/realms/'.self::REALM.'/protocol/openid-connect/token' => Http::response([
                'access_token' => 'super-secret-access-token',
                'id_token' => $this->idToken(['iss' => self::ISSUER, 'aud' => self::CLIENT_ID]),
            ], 500),
        ]);

        $this->withSession(['keycloak.oauth' => $this->oauthSession($state)]);
        $response = $this->get(route('keycloak.callback', ['state' => $state, 'code' => 'exchange-me']));

        $response->assertSessionHasErrors('oauth');
        $this->assertGuest();
        $this->assertStringNotContainsString('super-secret-access-token', (string) file_get_contents(storage_path('logs/laravel.log')));
    }

    public function test_logout_uses_configured_post_logout_redirect_uri(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'logout-sub']);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(self::POST_LOGOUT_REDIRECT_URI, $query['post_logout_redirect_uri']);
    }

    private function oauthSession(string $state): array
    {
        return [
            'state' => $state,
            'nonce' => self::NONCE,
            'verifier' => 'valid-verifier',
            'redirect_uri' => self::REDIRECT_URI,
        ];
    }

    private function idToken(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));

        return $header.'.'.$payload.'.'.$this->base64UrlEncode('signature');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
