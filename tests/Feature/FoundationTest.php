<?php

namespace Tests\Feature;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_application_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_check_reports_dependency_readiness(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'up',
                'checks' => [
                    'database' => 'up',
                    'cache' => 'up',
                    'queue' => 'up',
                ],
            ]);
    }

    public function test_readiness_alias_reports_dependency_readiness(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'up');
    }

    public function test_health_check_returns_unavailable_without_exposing_failure_details(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('database password must not be exposed'));

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.database', 'down')
            ->assertJsonMissing(['message' => 'database password must not be exposed']);
    }

    public function test_health_check_returns_unavailable_when_cache_is_down(): void
    {
        Cache::shouldReceive('store')
            ->once()
            ->andThrow(new RuntimeException('cache credentials must not be exposed'));

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.cache', 'down')
            ->assertJsonMissing(['message' => 'cache credentials must not be exposed']);
    }

    public function test_health_check_returns_unavailable_when_cache_write_fails(): void
    {
        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('cache write credentials must not be exposed'));
        Cache::shouldReceive('store')
            ->once()
            ->andReturn($cache);

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.cache', 'down')
            ->assertJsonMissing(['message' => 'cache write credentials must not be exposed']);
    }

    public function test_health_check_returns_unavailable_when_queue_is_down(): void
    {
        Queue::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('queue credentials must not be exposed'));

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.queue', 'down')
            ->assertJsonMissing(['message' => 'queue credentials must not be exposed']);
    }

    public function test_primary_application_routes_are_registered(): void
    {
        $this->assertNotNull(route('health'));
        $this->assertNotNull(route('readiness'));
        $this->assertNotNull(route('keycloak.redirect'));
        $this->assertNotNull(route('keycloak.callback'));
        $this->assertNotNull(route('logout'));
    }

    public function test_filament_admin_panel_boots(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_filament_admin_panel_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
