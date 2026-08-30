<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EnvironmentValidationTest extends TestCase
{
    /** @var array<string, string> */
    private array $environment = [
        'KEYCLOAK_BASE_URL' => 'https://keycloak.example.test',
        'KEYCLOAK_REALM' => 'umrah',
        'KEYCLOAK_CLIENT_ID' => 'procurement',
        'KEYCLOAK_CLIENT_SECRET' => 'ci-client-secret',
        'KEYCLOAK_REDIRECT_URI' => 'https://procurement.example.test/auth/keycloak/callback',
        'KEYCLOAK_POST_LOGOUT_REDIRECT_URI' => 'https://procurement.example.test',
    ];

    protected function tearDown(): void
    {
        foreach (array_keys($this->environment) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    public function test_required_environment_configuration_is_accepted(): void
    {
        $this->setEnvironment($this->environment);

        $this->assertSame(0, Artisan::call('app:validate-environment'));
        $this->assertStringContainsString('Environment configuration is valid.', Artisan::output());
    }

    public function test_required_environment_configuration_is_accepted_from_cached_configuration(): void
    {
        $this->setEnvironment($this->environment);

        foreach (array_keys($this->environment) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $this->assertSame(0, Artisan::call('app:validate-environment'));
        $this->assertStringContainsString('Environment configuration is valid.', Artisan::output());
    }

    public function test_missing_client_secret_is_rejected_without_printing_secret_values(): void
    {
        $environment = $this->environment;
        unset($environment['KEYCLOAK_CLIENT_SECRET']);
        $this->setEnvironment($environment);

        $this->assertSame(1, Artisan::call('app:validate-environment'));
        $output = Artisan::output();
        $this->assertStringContainsString('KEYCLOAK_CLIENT_SECRET', $output);
        $this->assertStringNotContainsString('ci-client-secret', $output);
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function setEnvironment(array $environment): void
    {
        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        config([
            'keycloak.base_url' => $environment['KEYCLOAK_BASE_URL'] ?? null,
            'keycloak.realm' => $environment['KEYCLOAK_REALM'] ?? null,
            'keycloak.client_id' => $environment['KEYCLOAK_CLIENT_ID'] ?? null,
            'keycloak.client_secret' => $environment['KEYCLOAK_CLIENT_SECRET'] ?? null,
            'keycloak.redirect_uri' => $environment['KEYCLOAK_REDIRECT_URI'] ?? null,
            'keycloak.post_logout_redirect_uri' => $environment['KEYCLOAK_POST_LOGOUT_REDIRECT_URI'] ?? null,
        ]);
    }
}
