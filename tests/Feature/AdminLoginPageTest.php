<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_admin_login_page_renders_local_sso_action(): void
    {
        $response = $this->get(route('filament.admin.auth.login'));

        $response->assertOk();
        $response->assertSee('Masuk dengan Keycloak');
        $response->assertSee(route('keycloak.redirect'), false);
    }

    public function test_unassigned_authenticated_user_can_reach_login_page_to_exit_session(): void
    {
        $response = $this->actingAs(User::factory()->create(['keycloak_sub' => 'unassigned-subject']))
            ->get(route('filament.admin.auth.login'));

        $response->assertOk();
        $response->assertSee('Akun ini belum mendapat akses ke aplikasi.');
        $response->assertSee('Keluar lalu masuk dengan akun lain');
    }
}
