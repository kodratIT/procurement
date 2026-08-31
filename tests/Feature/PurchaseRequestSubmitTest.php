<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\Activity;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ProcurementRequestDraftSaver;
use App\Services\ProcurementRequestSubmitter;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PurchaseRequestSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_assigns_unique_number_snapshots_approver_and_audits_context(): void
    {
        [$submitter, $office, $category] = $this->submitterContext();
        $approver = User::factory()->create(['name' => 'Procurement Reviewer']);
        $this->assignment($approver, $office, 'Manager');
        $request = $this->draft($submitter, $category);

        $submitted = app(ProcurementRequestSubmitter::class)->submit($request, $submitter);

        $this->assertSame(PurchaseRequestStatus::Submitted->value, $submitted->status);
        $this->assertMatchesRegularExpression('/^PR-\d{6}-\d{4}$/', $submitted->pr_number);
        $this->assertDatabaseHas('approval_instances', [
            'purchase_request_id' => $request->id,
            'requester_id' => $submitter->id,
            'submitted_by_id' => $submitter->id,
            'office_id' => $office->id,
            'workflow_reference' => 'standard-procurement',
        ]);
        $this->assertDatabaseHas('approval_instance_steps', [
            'approver_id' => $approver->id,
            'approver_name' => 'Procurement Reviewer',
            'approver_role' => 'Manager',
            'office_id' => $office->id,
        ]);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $request->id,
            'from_status' => PurchaseRequestStatus::Draft->value,
            'to_status' => PurchaseRequestStatus::Submitted->value,
            'event' => 'submitted',
            'decision' => 'submit',
            'actor_id' => $submitter->id,
            'office_id' => $office->id,
        ]);

        $activity = Activity::query()
            ->where('subject_type', PurchaseRequest::class)
            ->where('subject_id', $request->id)
            ->where('event', 'submitted')
            ->first();
        $this->assertNotNull($activity);
        $this->assertSame($submitter->id, $activity->causer_id);
        $this->assertSame($office->id, $activity->properties['office_id']);
    }

    public function test_invalid_submit_is_atomic_and_keeps_actionable_draft(): void
    {
        [$submitter, $office, $category] = $this->submitterContext();
        $approver = User::factory()->create();
        $this->assignment($approver, $office, 'Manager');
        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'reason' => 'Incomplete request',
            'items' => [],
        ], user: $submitter);

        try {
            app(ProcurementRequestSubmitter::class)->submit($request, $submitter);
            $this->fail('An invalid purchase request must not be submitted.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'At least one purchase request item is required before submission.',
            ], $exception->errors()['items']);
        }

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'status' => PurchaseRequestStatus::Draft->value,
            'pr_number' => 'DRAFT-'.$request->id,
        ]);
        $this->assertDatabaseMissing('approval_instances', ['purchase_request_id' => $request->id]);
        $this->assertDatabaseMissing('purchase_request_status_histories', ['purchase_request_id' => $request->id]);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $request->id,
            'event' => 'submitted',
        ]);
    }

    public function test_missing_approver_rejects_submit_without_changing_the_draft(): void
    {
        [$submitter, $office, $category] = $this->submitterContext();
        $request = $this->draft($submitter, $category);

        $caught = false;
        try {
            app(ProcurementRequestSubmitter::class)->submit($request, $submitter);
        } catch (ValidationException $exception) {
            $caught = true;
            $this->assertSame([
                'No eligible approver is configured for this purchase request scope. Contact procurement administration.',
            ], $exception->errors()['workflow']);
        }

        $this->assertTrue($caught);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'status' => PurchaseRequestStatus::Draft->value,
        ]);
    }

    public function test_submit_is_denied_outside_the_request_office_scope(): void
    {
        [$submitter, $office, $category] = $this->submitterContext();
        $approver = User::factory()->create();
        $this->assignment($approver, $office, 'Manager');
        $request = $this->draft($submitter, $category);
        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);
        app(ProcurementRequestSubmitter::class)->submit($request, $outsider);
    }

    /** @return array{User, Office, ProcurementCategory} */
    private function submitterContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $submitter = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create(['workflow_reference' => 'standard-procurement']);
        $assignment = $this->assignment($submitter, $office, 'Operasional');
        $this->actingAs($submitter);
        app(AccessContextService::class)->setContext($assignment);

        return [$submitter, $office, $category];
    }

    private function assignment(User $user, Office $office, string $roleName): UserAssignment
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

    private function draft(User $submitter, ProcurementCategory $category): PurchaseRequest
    {
        return app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'reason' => 'Operational supplies are required',
            'items' => [[
                'item_name' => 'Uniform',
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ], user: $submitter);
    }
}
