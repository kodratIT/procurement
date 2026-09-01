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
use App\Services\ProcurementReviewService;
use App\Services\PurchaseRequestTimeline;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReturnedPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_returns_request_with_required_actionable_note(): void
    {
        [$submitter, $reviewer, $office, $request] = $this->submittedRequest();
        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office, 'Pengadaan'));

        $returned = app(ProcurementReviewService::class)->returnToRequester(
            $request,
            'Please add the supplier specification.',
            $reviewer,
        );

        $this->assertSame(PurchaseRequestStatus::Returned->value, $returned->status);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $request->id,
            'from_status' => PurchaseRequestStatus::Submitted->value,
            'to_status' => PurchaseRequestStatus::Returned->value,
            'decision' => 'return',
            'note' => 'Please add the supplier specification.',
            'actor_id' => $reviewer->id,
            'office_id' => $office->id,
        ]);
        $this->assertSame($submitter->id, $returned->requester_id);
    }

    public function test_requester_can_correct_returned_request_and_resubmit_without_losing_history(): void
    {
        [$submitter, $reviewer, $office, $request] = $this->submittedRequest();
        $number = $request->pr_number;
        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office, 'Pengadaan'));
        $request = app(ProcurementReviewService::class)->returnToRequester($request, 'Correct the quantity.', $reviewer);

        $this->actingAs($submitter);
        app(AccessContextService::class)->setContext($this->assignment($submitter, $office, 'Operasional'));
        $corrected = app(ProcurementRequestDraftSaver::class)->save([
            'reason' => 'Corrected operational supplies request',
            'items' => [['item_name' => 'Uniform', 'quantity' => 3, 'unit_price' => 100]],
        ], $request->refresh(), $submitter);
        $resubmitted = app(ProcurementRequestSubmitter::class)->submit($corrected, $submitter);

        $this->assertSame($number, $resubmitted->pr_number);
        $this->assertSame(PurchaseRequestStatus::Submitted->value, $resubmitted->status);
        $this->assertSame('300.00', $resubmitted->total_amount);
        $this->assertSame(4, $resubmitted->statusHistories()->count());
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'event' => 'correction_saved',
            'to_status' => PurchaseRequestStatus::Returned->value,
            'actor_id' => $submitter->id,
        ]);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $request->id,
            'event' => 'resubmitted',
            'from_status' => PurchaseRequestStatus::Returned->value,
            'to_status' => PurchaseRequestStatus::Submitted->value,
        ]);
        $this->assertSame(2, $resubmitted->approvalInstances()->count());
        $timeline = app(PurchaseRequestTimeline::class)->for($resubmitted, $submitter);
        $this->assertSame(['submitted', 'returned', 'correction_saved', 'resubmitted'], $timeline->pluck('event')->all());
    }

    /** @return array{User, User, Office, PurchaseRequest} */
    private function submittedRequest(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $submitter = User::factory()->create();
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create(['workflow_reference' => 'standard-procurement']);
        $this->assignment($submitter, $office, 'Operasional');
        $this->assignment($reviewer, $office, 'Pengadaan');
        $approver = User::factory()->create();
        $this->assignment($approver, $office, 'Manager');
        $this->actingAs($submitter);
        app(AccessContextService::class)->setContext($this->assignment($submitter, $office, 'Operasional'));
        $request = app(ProcurementRequestDraftSaver::class)->save([
            'category_id' => $category->id,
            'reason' => 'Operational supplies are required',
            'items' => [['item_name' => 'Uniform', 'quantity' => 2, 'unit_price' => 100]],
        ], user: $submitter);
        $request = app(ProcurementRequestSubmitter::class)->submit($request, $submitter);

        return [$submitter, $reviewer, $office, $request];
    }

    private function assignment(User $user, Office $office, string $roleName): UserAssignment
    {
        $existing = $user->assignments()->where('office_id', $office->id)->first();
        if ($existing !== null) {
            return $existing;
        }

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
