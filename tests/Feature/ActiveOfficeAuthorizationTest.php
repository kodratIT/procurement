<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ActiveOfficeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActiveOfficeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_current_assignment_is_selected_and_switch_is_restricted(): void
    {
        $user = User::factory()->create();
        $allowed = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);
        $other = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);
        UserAssignment::factory()->create([
            'user_id' => $user->id, 'office_id' => $allowed->id,
            'valid_from' => Carbon::yesterday(), 'is_primary' => true, 'is_active' => true,
        ]);
        UserAssignment::factory()->create([
            'user_id' => $user->id, 'office_id' => $other->id,
            'valid_from' => Carbon::yesterday(), 'is_primary' => false, 'is_active' => true,
        ]);

        $this->actingAs($user);
        $context = app(ActiveOfficeContext::class);

        $this->assertSame($allowed->id, $context->id());
        $context->set($other);
        $this->assertSame($other->id, $context->id());
    }

    public function test_expired_assignment_cannot_be_used(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);
        UserAssignment::factory()->create([
            'user_id' => $user->id, 'office_id' => $office->id,
            'valid_from' => Carbon::yesterday()->subYear(),
            'valid_until' => Carbon::yesterday()->subDay(), 'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->assertNull(app(ActiveOfficeContext::class)->current());
    }
}
