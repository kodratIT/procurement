<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Services\AccessContextService;
use App\Services\AttachmentService;
use App\Services\QuotationComparisonService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuotationComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_calculates_each_line_and_overall_total_for_multiple_quotes(): void
    {
        $request = PurchaseRequest::factory()->create(['total_amount' => 999999]);
        DB::table('purchase_requests')->where('id', $request->id)->update(['total_amount' => 999999]);
        $firstLine = $request->items()->create(['item_name' => 'Uniform', 'quantity' => 2, 'unit_price' => 10]);
        $secondLine = $request->items()->create(['item_name' => 'Bag', 'quantity' => 3, 'unit_price' => 20]);
        $first = Quotation::factory()->for($request)->for(Vendor::factory()->create(['name' => 'Vendor A']))->create(['total_amount' => 1]);
        $second = Quotation::factory()->for($request)->for(Vendor::factory()->create(['name' => 'Vendor B']))->create(['total_amount' => 1]);
        $first->items()->create(['purchase_request_item_id' => $firstLine->id, 'quantity' => 2, 'unit_price' => 100]);
        $first->items()->create(['purchase_request_item_id' => $secondLine->id, 'quantity' => 3, 'unit_price' => 200]);
        $second->items()->create(['purchase_request_item_id' => $firstLine->id, 'quantity' => 2, 'unit_price' => 125]);
        $second->items()->create(['purchase_request_item_id' => $secondLine->id, 'quantity' => 3, 'unit_price' => 150]);
        $second->update(['tax_amount' => 50, 'shipping_amount' => 25]);

        $comparison = app(QuotationComparisonService::class)->compare($request);
        $rows = collect($comparison['quotations'])->keyBy('id');

        $this->assertSame('800.00', $rows[$first->id]['subtotal_amount']);
        $this->assertSame('800.00', $rows[$first->id]['total_amount']);
        $this->assertSame('700.00', $rows[$second->id]['subtotal_amount']);
        $this->assertSame('775.00', $rows[$second->id]['total_amount']);
        $this->assertSame('600.00', $rows[$first->id]['line_totals'][$secondLine->id]);
        $this->assertSame('775.00', $comparison['overall_totals'][$second->id]);
        $this->assertSame('999999.00', $request->fresh()->total_amount);
    }

    public function test_recommendation_rejects_a_quote_with_uncovered_request_lines(): void
    {
        [$reviewer, $request] = $this->reviewContext();
        $line = $request->items()->create(['item_name' => 'Uniform', 'quantity' => 2, 'unit_price' => 10]);
        $uncovered = $request->items()->create(['item_name' => 'Bag', 'quantity' => 1, 'unit_price' => 20]);
        $quotation = Quotation::factory()->for($request)->create();
        $quotation->items()->create(['purchase_request_item_id' => $line->id, 'quantity' => 2, 'unit_price' => 100]);

        $this->actingAs($reviewer);
        $coverage = app(QuotationComparisonService::class)->validateLineCoverage($quotation->fresh(), $request);
        $this->assertSame([$uncovered->id], $coverage['missing']);
        $this->assertFalse($coverage['complete']);

        try {
            app(QuotationComparisonService::class)->recommend($request, $quotation, 'Best lead time.', $reviewer);
            $this->fail('An incomplete quotation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quotation', $exception->errors());
        }

        $this->assertDatabaseCount('quotation_recommendations', 0);
    }

    public function test_required_reason_and_evidence_are_enforced_and_recommendation_is_versioned(): void
    {
        [$reviewer, $request] = $this->reviewContext([
            'requires_recommendation_reason' => true,
            'requires_recommendation_evidence' => true,
        ]);
        $line = $request->items()->create(['item_name' => 'Uniform', 'quantity' => 2, 'unit_price' => 10]);
        $quotation = Quotation::factory()->for($request)->create();
        $quotation->items()->create(['purchase_request_item_id' => $line->id, 'quantity' => 2, 'unit_price' => 100]);
        try {
            app(QuotationComparisonService::class)->recommend($request, $quotation, '', $reviewer);
            $this->fail('A required recommendation reason must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        try {
            app(QuotationComparisonService::class)->recommend($request, $quotation, 'Lowest comparable total.', [], $reviewer);
            $this->fail('A required recommendation attachment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }

        Storage::fake('private');
        $attachment = app(AttachmentService::class)->store(
            UploadedFile::fake()->createWithContent('quote.txt', 'vendor evidence'),
            $quotation,
            $reviewer,
            'quotation',
        );
        $recommended = app(QuotationComparisonService::class)->recommend(
            $request,
            $quotation,
            'Lowest comparable total.',
            [$attachment->id],
            $reviewer,
        );

        $this->assertSame(1, $recommended->version);
        $this->assertSame($quotation->id, $request->fresh()->recommended_quotation_id);
        $this->assertSame('20.00', $request->fresh()->total_amount);
        $this->assertDatabaseHas('quotation_recommendations', [
            'purchase_request_id' => $request->id,
            'quotation_id' => $quotation->id,
            'version' => 1,
            'reason' => 'Lowest comparable total.',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'event' => 'quotation_recommended',
        ]);
    }

    public function test_recording_a_quotation_calculates_totals_and_stores_private_attachments(): void
    {
        [$reviewer, $request] = $this->reviewContext();
        $line = $request->items()->create(['item_name' => 'Uniform', 'quantity' => 2, 'unit_price' => 10]);
        Storage::fake('private');

        $quotation = app(QuotationComparisonService::class)->recordQuotation(
            $request,
            [
                'vendor_id' => Vendor::factory()->create()->id,
                'quotation_number' => 'QTN-001',
                'items' => [[
                    'purchase_request_item_id' => $line->id,
                    'quantity' => 2,
                    'unit_price' => 125,
                ]],
                'attachments' => [
                    UploadedFile::fake()->createWithContent('quotation.txt', 'quotation evidence'),
                ],
            ],
            $reviewer,
        );

        $this->assertSame('250.00', $quotation->fresh()->total_amount);
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => $quotation->getMorphClass(),
            'attachable_id' => $quotation->id,
            'collection' => 'quotation',
        ]);
        $this->assertCount(1, Storage::disk('private')->allFiles());
    }

    public function test_comparison_is_denied_outside_the_active_office_scope(): void
    {
        $otherOffice = Office::factory()->create();
        $otherRequest = PurchaseRequest::factory()->create(['office_id' => $otherOffice->id]);
        [$reviewer] = $this->reviewContext();

        $this->actingAs($reviewer);
        $this->expectException(AuthorizationException::class);

        app(QuotationComparisonService::class)->compare($otherRequest, $reviewer);
    }

    /** @param array<string, mixed> $categoryAttributes */
    private function reviewContext(array $categoryAttributes = []): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create($categoryAttributes);
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'category_id' => $category->id,
            'status' => PurchaseRequest::STATUS_PROCUREMENT_REVIEW,
            'total_amount' => 999999,
        ]);
        DB::table('purchase_requests')->where('id', $request->id)->update(['total_amount' => 999999]);
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $reviewer->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($assignment);

        return [$reviewer, $request];
    }
}
