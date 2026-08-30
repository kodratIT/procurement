<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\KeycloakUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KeycloakUserProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_upsert_synchronizes_claims_and_login_timestamps(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        config(['keycloak.provisioning_mode' => 'hybrid']);
        $provisioner = app(KeycloakUserProvisioner::class);

        $first = $provisioner->provision([
            'sub' => 'subject-1',
            'name' => 'First Name',
            'email' => 'first@example.test',
            'picture' => 'https://cdn.example.test/first.png',
        ]);

        $this->travel(1)->minute();
        $updated = $provisioner->provision([
            'sub' => 'subject-1',
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'picture' => 'https://cdn.example.test/updated.png',
        ]);

        $this->assertSame($first->id, $updated->id);
        $this->assertSame(1, User::query()->where('keycloak_sub', 'subject-1')->count());
        $this->assertDatabaseHas('users', [
            'keycloak_sub' => 'subject-1',
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'avatar' => 'https://cdn.example.test/updated.png',
            'is_active' => 1,
            'last_login_at' => '2026-08-30 12:01:00',
            'last_token_sync_at' => '2026-08-30 12:01:00',
        ]);
    }

    public function test_missing_subject_is_rejected_without_creating_a_local_user(): void
    {
        try {
            app(KeycloakUserProvisioner::class)->provision([
                'name' => 'Missing Subject',
                'email' => 'missing@example.test',
            ]);
            $this->fail('A missing Keycloak subject must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_pre_provisioned_mode_rejects_an_unknown_subject(): void
    {
        config(['keycloak.provisioning_mode' => 'pre-provisioned']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/not been provisioned/i');

        app(KeycloakUserProvisioner::class)->provision([
            'sub' => 'unknown-subject',
            'name' => 'Unknown User',
        ]);
    }

    public function test_disabled_claim_deactivates_existing_user(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'subject-1']);

        app(KeycloakUserProvisioner::class)->provision([
            'sub' => 'subject-1',
            'name' => 'Disabled User',
            'enabled' => false,
        ]);

        $this->assertFalse($user->fresh()->is_active);
        $this->assertNotNull($user->fresh()->last_token_sync_at);
    }

    public function test_keycloak_subject_cannot_be_changed_after_provisioning(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'subject-1']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/immutable/i');

        $user->update(['keycloak_sub' => 'subject-2']);
    }
}
