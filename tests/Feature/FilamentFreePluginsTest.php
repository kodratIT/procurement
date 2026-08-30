<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Traits\HasRoles;
use Tests\TestCase;

class FilamentFreePluginsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_baseline_uses_shield_and_permission_without_teams(): void
    {
        $this->assertSame(User::class, config('filament-shield.auth_provider_model'));
        $this->assertFalse((bool) config('permission.teams'));
        $this->assertContains(HasRoles::class, class_uses_recursive(User::class));
    }

    public function test_activity_log_is_enabled_with_the_existing_activity_table(): void
    {
        $this->assertTrue((bool) config('activitylog.enabled'));
        $this->assertSame('activity_log', config('activitylog.table_name'));
        $this->assertSame(
            'Spatie\\Activitylog\\Models\\Activity',
            config('activitylog.activity_model'),
        );
    }
}
