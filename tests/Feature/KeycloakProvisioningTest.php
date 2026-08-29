<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Services\Auth\KeycloakUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user->offices()->attach($office);

        $updated = $provisioner->provision([
            'sub' => 'immutable-sub-1',
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
        ]);

        $this->assertSame($user->id, $updated->id);
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('updated@example.test', $updated->email);
        $this->assertCount(1, User::all());
        $this->assertTrue($updated->offices()->whereKey($office)->exists());
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
}
