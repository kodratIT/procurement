<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_shell_is_available_and_protected(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_health_check_is_available_to_an_assigned_user(): void
    {
        $this->actingAs($this->assignedUser())
            ->get('/admin/health')
            ->assertOk()
            ->assertSee('Kesehatan Sistem')
            ->assertSee('Koneksi DB')
            ->assertDontSee('APP_KEY');
    }

    public function test_panel_navigation_contains_only_safe_shell_items(): void
    {
        $this->actingAs($this->assignedUser())->get('/admin');

        $labels = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->map(fn ($item) => $item->getLabel());

        $this->assertTrue($labels->contains('Dashboard'));
        $this->assertTrue($labels->contains('Kesehatan Sistem'));
    }

    private function assignedUser(): User
    {
        $office = Office::create(['code' => 'HQ', 'name' => 'Head Office']);
        $user = User::factory()->create();

        UserAssignment::create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $user;
    }
}
