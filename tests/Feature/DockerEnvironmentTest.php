<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DockerEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_base_office_and_assigned_user(): void
    {
        $this->seed();

        $this->assertDatabaseHas('offices', [
            'code' => 'HO',
            'name' => 'Head Office Jakarta',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $office = Office::where('code', 'HO')->firstOrFail();

        $this->assertTrue($user->offices()->where('offices.id', $office->id)->exists());
        $this->assertTrue($user->canAccessPanel(app(\Filament\PanelRegistry::class)->get('admin')));
    }

    public function test_environment_file_exposes_local_docker_stack_variables(): void
    {
        // The default .env.example targets the local Docker stack (PostgreSQL + Redis).
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_CONNECTION=pgsql', $example);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $example);
        $this->assertStringContainsString('DB_DATABASE=umrah_procurement', $example);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $example);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $example);
        $this->assertStringContainsString('CACHE_STORE=redis', $example);
        $this->assertStringContainsString('REDIS_HOST=127.0.0.1', $example);
    }
}
