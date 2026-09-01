<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ProcurementRequestDraftSaver;
use App\Services\ProcurementRequestSubmitter;
use App\Services\PurchaseRequestTimeline;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchaseRequestTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_receives_ordered_timeline_with_decision_actor_and_context(): void
    {
        [$submitter, $office, $request] = $this->submittedRequest();

        $timeline = app(PurchaseRequestTimeline::class)->for($request, $submitter);

        $this->assertCount(1, $timeline);
        $entry = $timeline->first();
        $this->assertSame(PurchaseRequestStatus::Draft->value, $entry->from_status);
        $this->assertSame(PurchaseRequestStatus::Submitted->value, $entry->to_status);
        $this->assertSame('submit', $entry->decision);
        $this->assertSame($submitter->id, $entry->actor_id);
        $this->assertSame($office->id, $entry->office_id);
        $this->assertSame($submitter->id, $entry->context['requester_id']);
        $this->assertSame($office->id, $entry->context['office_id']);
        $this->assertNotNull($entry->created_at);
        $this->assertSame('Operasional', $entry->role?->name);
    }

    public function test_timeline_denies_a_user_outside_the_request_scope(): void
    {
        [$submitter, , $request] = $this->submittedRequest();
        $outsider = User::factory()->create();
        auth()->logout();
        $office = Office::factory()->create();
        $this->assign($outsider, $office, 'Viewer');
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);
        app(PurchaseRequestTimeline::class)->for($request, $outsider);
    }

    public function test_policy_allows_timeline_for_submitted_requests_not_only_drafts(): void
    {
        [$submitter, , $request] = $this->submittedRequest();

        $this->assertTrue($submitter->can('viewTimeline', $request));
        $this->assertTrue($submitter->can('view', $request));
    }

    /** @return array{User, Office, PurchaseRequest} */
    private function submittedRequest(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $submitter = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create(['workflow_reference' => 'standard-procurement']);
        $submitterAssignment = $this->assign($submitter, $office, 'Operasional');
        $approver = User::factory()->create();
        $this->assign($approver, $office, 'Manager');
        $this->actingAs($submitter);
        app(AccessContextService::class)->setContext($submitterAssignment);
        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'reason' => 'Operational supplies are required',
            'items' => [['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100]],
        ], user: $submitter);
        $request = app(ProcurementRequestSubmitter::class)->submit($request, $submitter);

        return [$submitter, $office, $request];
    }

    private function assign(User $user, Office $office, string $roleName): UserAssignment
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
    }
}
