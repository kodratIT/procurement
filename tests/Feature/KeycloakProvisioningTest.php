<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\Auth\KeycloakUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KeycloakProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_keycloak_subject_is_upserted_and_claims_are_synchronised(): void
    {
        $office = Office::factory()->create();
        $provisioner = app(KeycloakUserProvisioner::class);

        $user = $provisioner->provision([
            'sub' => 'immutable-sub-1',
            'name' => 'First Name',
            'email' => 'first@example.test',
        ]);
        UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'is_active' => true,
        ]);

        $updated = $provisioner->provision([
            'sub' => 'immutable-sub-1',
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
        ]);

        $this->assertSame($user->id, $updated->id);
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('updated@example.test', $updated->email);
        $this->assertCount(1, User::all());
        $this->assertTrue($updated->assignments()->where('office_id', $office->id)->exists());
        $this->assertTrue($updated->hasActiveAssignment());
    }

    public function test_subject_cannot_take_an_email_owned_by_another_subject(): void
    {
        User::factory()->create([
            'keycloak_sub' => 'immutable-sub-1',
            'email' => 'claimed@example.test',
        ]);

        $this->expectException(ValidationException::class);

        app(KeycloakUserProvisioner::class)->provision([
            'sub' => 'immutable-sub-2',
            'name' => 'Another User',
            'email' => 'claimed@example.test',
        ]);
    }

    public function test_user_without_office_assignment_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'immutable-sub-1']);

        $this->assertFalse($user->canAccessPanel(app('filament')->getPanel('admin')));
    }

    public function test_keycloak_subject_is_immutable_after_first_provisioning(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'immutable-sub-1']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/immutable/i');

        $user->update(['keycloak_sub' => 'another-sub']);
    }

    public function test_keycloak_subject_can_be_set_from_null_and_is_unique(): void
    {
        User::factory()->create(['keycloak_sub' => 'immutable-sub-1']);
        $second = User::factory()->create(['keycloak_sub' => null]);

        $this->assertSame('immutable-sub-1', User::query()->where('keycloak_sub', 'immutable-sub-1')->value('keycloak_sub'));
        $this->assertNull($second->fresh()->keycloak_sub);
    }

    public function test_expired_or_inactive_assignment_denies_panel_access(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'immutable-sub-1']);
        $office = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);

        $expired = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday()->subYear(),
            'valid_until' => Carbon::yesterday()->subDay(),
            'is_active' => true,
        ]);

        $this->assertFalse($user->canAccessPanel(app('filament')->getPanel('admin')));
        $this->assertFalse($user->hasActiveAssignment());

        $expired->update(['is_active' => false]);
        $this->assertFalse($user->hasActiveAssignment());
    }

    public function test_assignment_defaults_to_viewer_role_when_none_is_specified(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);

        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'role' => null,
        ]);

        $this->assertSame(UserAssignment::DEFAULT_ROLE, $assignment->fresh()->role);
        $this->assertSame('Viewer', $assignment->fresh()->role);
    }
}
