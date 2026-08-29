<?php

namespace Tests\Feature;

use App\Console\Commands\MakeFilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MakeFilamentUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:filament-user', $commands);
        $this->assertSame(
            MakeFilamentUser::class,
            $commands['make:filament-user']::class
        );
    }

    public function test_command_refuses_to_run_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('make:filament-user', [
            '--name' => 'Prod User',
            '--email' => 'prod@example.com',
            '--password' => 'secret123',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'prod@example.com']);
    }

    public function test_command_runs_in_local_environment(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $this->artisan('make:filament-user', [
            '--name' => 'Local User',
            '--email' => 'local@example.com',
            '--password' => 'secret123',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'local@example.com']);
    }
}
