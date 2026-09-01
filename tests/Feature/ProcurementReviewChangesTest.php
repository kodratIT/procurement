<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFieldValue;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Services\AccessContextService;
use App\Services\ProcurementReviewService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProcurementReviewChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_review_edits_capture_immutable_before_after_context_before_persistence(): void
    {
        [$reviewer, $office, $category, $request, $item, $field] = $this->reviewContext();
        $value = PurchaseRequestFieldValue::fromField($request, $field, 'Original color');
        $value->save();

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office));
        $vendor = Vendor::factory()->create();

        $updated = app(ProcurementReviewService::class)->edit(
            $request,
            [
                'items' => [[
                    'id' => $item->id,
                    'quantity' => 4,
                    'unit_price' => 125,
                    'specifications' => ['material' => 'cotton', 'color' => 'black'],
                ]],
                'fields' => ['review_color' => 'Black'],
                'vendor_id' => $vendor->id,
            ],
            'Negotiated quantity and price with procurement.',
            $reviewer,
        );
        $activity = Activity::query()
            ->where('subject_type', PurchaseRequest::class)
            ->where('subject_id', $request->id)
            ->where('event', 'review_edited')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('4.00', $updated->items->first()->quantity);
        $this->assertSame('125.00', $updated->items->first()->unit_price);
        $this->assertSame('500.00', $updated->total_amount);
        $this->assertSame($vendor->id, $updated->vendor_id);
        $this->assertSame('2.00', $activity->properties['before']['items'][$item->id]['quantity']);
        $this->assertSame('Black', $updated->fieldValues->firstWhere('field_key', 'review_color')->value);
        $this->assertSame('4.00', $activity->properties['after']['items'][$item->id]['quantity']);
        $this->assertSame('100.00', $activity->properties['before']['items'][$item->id]['unit_price']);
        $this->assertSame('125.00', $activity->properties['after']['items'][$item->id]['unit_price']);
        $this->assertSame('Original color', $activity->properties['before']['fields']['review_color']);
        $this->assertSame('Pengadaan', $activity->properties['role']);
        $this->assertSame($reviewer->id, $activity->properties['actor_id']);
        $this->assertSame('Black', $activity->properties['after']['fields']['review_color']);
        $this->assertNull($activity->properties['before']['vendor_id']);
        $this->assertSame($vendor->id, $activity->properties['after']['vendor_id']);
        $this->assertSame('Negotiated quantity and price with procurement.', $activity->properties['reason']);
        $this->assertNotNull($activity->properties['timestamp']);
    }

    public function test_review_rejects_immutable_identity_and_uncontrolled_fields_without_persistence(): void
    {
        [$reviewer, $office, $category, $request] = $this->reviewContext();
        $originalTitle = $request->title;

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office));

        try {
            app(ProcurementReviewService::class)->edit(
                $request,
                ['title' => 'Tampered title'],
                'Attempted identity mutation.',
                $reviewer,
            );
            $this->fail('Uncontrolled review fields must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('changes', $exception->errors());
        }

        $this->assertSame($originalTitle, $request->refresh()->title);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $request->id,
            'event' => 'review_edited',
        ]);
    }

    /** @return array{User, Office, ProcurementCategory, PurchaseRequest, PurchaseRequestItem, ProcurementField} */
    private function reviewContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create();
        $field = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'review_color',
            'label' => 'Review color',
            'editable_stage' => ProcurementField::EDITABLE_STAGE_REVIEW,
        ]);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'category_id' => $category->id,
            'status' => 'submitted',
            'title' => 'Original request',
        ]);
        $item = $request->items()->create([
            'item_name' => 'Uniform',
            'quantity' => 2,
            'unit_price' => 100,
            'specifications' => ['material' => 'cotton'],
        ]);
        $request->refresh()->syncTotals();

        return [$reviewer, $office, $category, $request->refresh(), $item, $field];
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
