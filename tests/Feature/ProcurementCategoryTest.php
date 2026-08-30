<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Support\ProcurementCategoryConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_stores_typed_configuration_flags_and_references(): void
    {
        $category = ProcurementCategory::factory()->create([
            'code' => 'HOTEL',
            'type' => ProcurementCategoryType::SERVICE,
            'requires_batch' => true,
            'requires_jamaah' => false,
            'requires_vendor' => true,
            'requires_quotation' => true,
            'requires_receipt' => true,
            'requires_invoice' => true,
            'requires_po' => true,
            'workflow_reference' => 'hotel-procurement',
            'number_template' => 'purchase_request_hotel',
        ]);

        $configuration = $category->refresh()->configuration();

        $this->assertInstanceOf(ProcurementCategoryConfiguration::class, $configuration);
        $this->assertSame(ProcurementCategoryType::SERVICE, $configuration->type);
        $this->assertTrue($configuration->requiresBatch());
        $this->assertFalse($configuration->requiresJamaah());
        $this->assertTrue($configuration->requiresVendor());
        $this->assertTrue($configuration->requiresQuotation());
        $this->assertTrue($configuration->requiresReceipt());
        $this->assertTrue($configuration->requiresInvoice());
        $this->assertTrue($configuration->requiresPurchaseOrder());
        $this->assertSame('hotel-procurement', $configuration->workflowReference);
        $this->assertSame('purchase_request_hotel', $configuration->numberTemplate);
    }

    public function test_invalid_category_type_is_rejected_by_the_model(): void
    {
        $this->expectException(\ValueError::class);

        ProcurementCategory::factory()->create(['type' => 'invalid']);
    }

    public function test_referenced_category_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $category = ProcurementCategory::factory()->create();
        $purchaseRequest = PurchaseRequest::factory()->for($category, 'category')->create();

        try {
            $category->delete();
            $this->fail('A category referenced by a purchase request must not be deleted.');
        } catch (QueryException) {
            $this->assertModelExists($category);
        }

        $category->deactivate();
        $purchaseRequest->refresh();

        $this->assertFalse($category->refresh()->is_active);
        $this->assertNotNull($category->disabled_at);
        $this->assertModelExists($purchaseRequest);
        $this->assertTrue($purchaseRequest->category->is($category));
    }

    public function test_inactive_category_is_excluded_from_new_request_options(): void
    {
        $activeCategory = ProcurementCategory::factory()->create(['code' => 'ACTIVE']);
        $inactiveCategory = ProcurementCategory::factory()->create(['code' => 'INACTIVE']);
        $inactiveCategory->deactivate();

        $availableCategories = ProcurementCategory::availableForNewPurchaseRequests()->pluck('code')->all();

        $this->assertContains($activeCategory->code, $availableCategories);
        $this->assertNotContains($inactiveCategory->code, $availableCategories);
    }

    public function test_new_purchase_request_rejects_an_inactive_category(): void
    {
        $category = ProcurementCategory::factory()->create();
        $category->deactivate();

        $this->expectException(ValidationException::class);

        PurchaseRequest::factory()->for($category, 'category')->create();
    }
}
