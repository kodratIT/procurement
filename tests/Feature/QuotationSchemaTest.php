<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuotationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_aggregate_tables_and_recommendation_fields_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('quotations', [
            'id', 'purchase_request_id', 'vendor_id', 'quotation_number', 'quoted_at',
            'valid_until', 'currency', 'subtotal_amount', 'discount_amount', 'tax_amount',
            'shipping_amount', 'total_amount', 'status', 'notes', 'created_by_id',
        ]));
        $this->assertTrue(Schema::hasColumns('quotation_items', [
            'id', 'quotation_id', 'purchase_request_item_id', 'quantity', 'unit_price',
            'line_total', 'total_price', 'notes', 'specifications',
        ]));
        $this->assertTrue(Schema::hasColumns('quotation_recommendations', [
            'purchase_request_id', 'quotation_id', 'vendor_id', 'version', 'reason',
            'evidence_attachment_ids', 'comparison_snapshot',
        ]));
        $this->assertTrue(Schema::hasColumns('purchase_requests', [
            'recommended_quotation_id', 'recommendation_reason', 'recommendation_version',
            'recommended_at', 'recommended_by_id',
        ]));
    }

    public function test_quotation_items_map_only_through_their_quotation_and_pr_item_relationships(): void
    {
        $request = PurchaseRequest::factory()->create();
        $requestItem = $request->items()->create([
            'item_name' => 'Uniform',
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $quotation = Quotation::factory()->for($request)->for(Vendor::factory())->create();
        $item = $quotation->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'quantity' => 2,
            'unit_price' => 125.50,
            'notes' => 'Cotton option',
        ]);

        $this->assertInstanceOf(PurchaseRequest::class, $quotation->purchaseRequest()->withoutGlobalScopes()->first());
        $this->assertInstanceOf(Vendor::class, $quotation->vendor);
        $this->assertTrue($item->quotation->is($quotation));
        $this->assertTrue($item->purchaseRequestItem->is($requestItem));
        $this->assertSame('251.00', $item->line_total);
        $this->assertSame('251.00', $item->total_price);
    }

    public function test_new_quotation_cannot_use_an_inactive_vendor(): void
    {
        $request = PurchaseRequest::factory()->create();
        $vendor = Vendor::factory()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);

        Quotation::factory()->for($request)->for($vendor)->create();
    }
}
