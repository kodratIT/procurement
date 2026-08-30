<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_without_active_assignment_receives_actionable_forbidden_response(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'unassigned-subject']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
        $response->assertSee('Access has not been granted to this application. Contact an administrator.');
    }

    public function test_expired_assignment_is_denied_by_the_panel_gate(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'expired-subject']);
        $office = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday()->subYear(),
            'valid_until' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
        $response->assertSee('Access has not been granted to this application. Contact an administrator.');
    }

    public function test_unassigned_panel_request_queries_no_business_data_before_denial(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'unassigned-subject']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->actingAs($user)->get('/admin');

        $businessQueries = array_filter($queries, fn (string $query): bool => str_contains($query, 'procurement_') || str_contains($query, 'purchase_request'));
        $this->assertSame([], array_values($businessQueries));
    }
}
