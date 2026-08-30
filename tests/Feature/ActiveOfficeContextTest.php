<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ActiveOfficeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveOfficeContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_facade_resolves_and_switches_only_assigned_offices(): void
    {
        $user = User::factory()->create();
        $first = Office::factory()->create();
        $second = Office::factory()->create();
        UserAssignment::factory()->create(['user_id' => $user->id, 'office_id' => $first->id, 'is_primary' => true]);
        UserAssignment::factory()->create(['user_id' => $user->id, 'office_id' => $second->id]);
        $unassigned = Office::factory()->create();

        $this->actingAs($user);
        $context = app(ActiveOfficeContext::class);

        $this->assertSame($first->id, $context->id());
        $context->set($second);
        $this->assertSame($second->id, $context->id());
        $this->assertFalse($context->hasAccess($unassigned));
    }
}
