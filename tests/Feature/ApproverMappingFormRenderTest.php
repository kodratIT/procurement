<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApproverMappingFormRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_mapping_create_page_renders_without_errors(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $office = Office::factory()->create();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role' => 'Admin',
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        app(AccessContextService::class)->setContext($assignment);

        $response = $this->get(ApproverMappingResource::getUrl('create'));

        $response->assertOk();
    }
}
