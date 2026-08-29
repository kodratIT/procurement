<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelUITest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_login_page_is_accessible(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_health_check_page_requires_authentication(): void
    {
        $this->get('/admin/health')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_health_check_page_is_accessible_to_authorized_user(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get('/admin/health')
            ->assertOk()
            ->assertSee('Kesehatan Sistem');
    }

    public function test_dashboard_is_accessible_to_authorized_user(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_panel_has_navigation_items(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)->get('/admin');

        $navigation = Filament::getNavigation();

        $labels = collect($navigation)
            ->flatMap(fn ($group) => $group->getItems())
            ->map(fn ($item) => $item->getLabel());

        $this->assertTrue(
            $labels->contains('Dashboard'),
            'Dashboard navigation item is missing'
        );

        $this->assertTrue(
            $labels->contains('Kesehatan Sistem'),
            'Health check navigation item is missing'
        );
    }

    public function test_application_home_redirects_to_admin_dashboard(): void
    {
        $this->get('/')->assertRedirect();
    }

    public function test_not_found_page_uses_custom_error_view(): void
    {
        $this->get('/this-page-does-not-exist-xyz')
            ->assertStatus(404)
            ->assertSee('Halaman Tidak Ditemukan');
    }

    private function makeAdminUser(): User
    {
        $office = Office::create(['code' => 'HQ', 'name' => 'Head Office']);

        $user = User::factory()->create([
            'name' => 'Admin F1.2',
            'email' => 'admin-f12@example.com',
        ]);

        $user->offices()->attach($office);

        return $user;
    }
}
