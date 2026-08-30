<?php

namespace Tests\Feature;

use Tests\TestCase;

class InfrastructureConfigurationTest extends TestCase
{
    public function test_application_uses_the_expected_infrastructure_defaults(): void
    {
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame('redis', config('cache.stores.redis.driver'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertStringContainsString(
            "'driver' => env('SESSION_DRIVER', 'redis')",
            file_get_contents(base_path('config/session.php')),
        );
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
    }

    public function test_environment_example_contains_no_secret_values_and_docker_services(): void
    {
        $environment = file_get_contents(base_path('.env.example'));
        $compose = file_get_contents(base_path('compose.yaml'));

        $this->assertIsString($environment);
        $this->assertIsString($compose);
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $environment);
        $this->assertStringContainsString('DB_PASSWORD=', $environment);
        $this->assertStringContainsString('REDIS_PASSWORD=null', $environment);
        $this->assertStringContainsString('postgres:', $compose);
        $this->assertStringContainsString('redis:', $compose);
        $this->assertStringContainsString('condition: service_healthy', $compose);
    }
}
