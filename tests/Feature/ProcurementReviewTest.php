<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\AssignmentScope;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ProcurementReviewService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProcurementReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_queue_and_forward_are_limited_to_the_assignment_scope(): void
    {
        [$reviewer, $office, $category] = $this->reviewerContext();
        $otherOffice = Office::factory()->create();
        $otherCategory = ProcurementCategory::factory()->create();
        $inScope = $this->submittedRequest($office, $category);
        $outOfScope = $this->submittedRequest($otherOffice, $otherCategory);
        $outOfCategory = $this->submittedRequest($office, $otherCategory);

        $this->actingAs($reviewer);
        $assignment = $this->assignment($reviewer, $office);
        AssignmentScope::create([
            'assignment_id' => $assignment->id,
            'scope_type' => 'category',
            'scope_id' => $category->id,
        ]);
        app(AccessContextService::class)->setContext($assignment);

        $queue = app(ProcurementReviewService::class)->reviewQueue($reviewer)->get();
        $forwarded = app(ProcurementReviewService::class)->forward($inScope, $reviewer);

        $this->assertSame([$inScope->id], $queue->pluck('id')->all());
        $this->assertSame(PurchaseRequestStatus::ProcurementReview->value, $forwarded->status);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $inScope->id,
            'from_status' => PurchaseRequestStatus::Submitted->value,
            'to_status' => PurchaseRequestStatus::ProcurementReview->value,
            'decision' => 'forward',
        ]);
        $this->assertDatabaseMissing('purchase_request_status_histories', [
            'purchase_request_id' => $outOfScope->id,
            'decision' => 'forward',
        ]);
        $this->assertNotContains($outOfCategory->id, $queue->pluck('id')->all());
    }

    public function test_return_requires_a_reason_and_records_returned_status(): void
    {
        [$reviewer, $office, $category] = $this->reviewerContext();
        $request = $this->submittedRequest($office, $category);

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office));

        try {
            app(ProcurementReviewService::class)->returnToRequester($request, ' ', $reviewer);
            $this->fail('A return without a reason must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['A reason is required when returning a purchase request.'], $exception->errors()['reason']);
        }

        $returned = app(ProcurementReviewService::class)->returnToRequester($request, 'Add the missing specification.', $reviewer);

        $this->assertSame(PurchaseRequestStatus::Returned->value, $returned->status);
        $this->assertDatabaseHas('purchase_request_status_histories', [
            'purchase_request_id' => $request->id,
            'to_status' => PurchaseRequestStatus::Returned->value,
            'decision' => 'return',
            'note' => 'Add the missing specification.',
        ]);
    }

    public function test_reviewer_cannot_review_a_request_from_another_office(): void
    {
        [$reviewer, $office, $category] = $this->reviewerContext();
        $otherRequest = $this->submittedRequest(Office::factory()->create(), $category);

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office));

        $this->expectException(AuthorizationException::class);

        app(ProcurementReviewService::class)->forward($otherRequest, $reviewer);
    }

    /** @return array{User, Office, ProcurementCategory} */
    private function reviewerContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create(['workflow_reference' => 'standard-procurement']);

        return [$reviewer, $office, $category];
    }

    private function submittedRequest(Office $office, ProcurementCategory $category): PurchaseRequest
    {
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'category_id' => $category->id,
            'status' => PurchaseRequestStatus::Submitted->value,
        ]);
        $request->items()->create([
            'item_name' => 'Uniform',
            'quantity' => 2,
            'unit_price' => 100,
            'description' => 'Original description',
            'specifications' => ['material' => 'cotton'],
        ]);
        $request->refresh()->syncTotals();

        return $request->refresh();
    }

    private function assignment(User $user, Office $office): UserAssignment
    {
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();

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
