<?php

namespace Tests\Feature;

use App\Models\ProcurementItem;
use App\Models\Vendor;
use App\Models\VendorItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_schema_and_item_relationships_store_reference_prices(): void
    {
        $this->assertTrue(Schema::hasColumns('vendors', [
            'code',
            'name',
            'vendor_type',
            'contact_name',
            'phone',
            'email',
            'address',
            'tax_number',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('vendor_items', [
            'vendor_id',
            'item_id',
            'reference_price',
            'currency',
            'is_active',
        ]));

        $vendor = Vendor::factory()->create();
        $item = ProcurementItem::factory()->create();
        $vendorItem = VendorItem::factory()->create([
            'vendor_id' => $vendor->id,
            'item_id' => $item->id,
            'reference_price' => '125000.50',
        ]);

        $this->assertTrue($vendor->items->contains($vendorItem));
        $this->assertTrue($vendorItem->vendor->is($vendor));
        $this->assertTrue($vendorItem->item->is($item));
        $this->assertSame('125000.50', $vendorItem->reference_price);
    }

    public function test_vendor_deactivation_preserves_historical_item_links_and_excludes_vendor_from_new_transactions(): void
    {
        $activeVendor = Vendor::factory()->create(['code' => 'ACTIVE-VENDOR']);
        $inactiveVendor = Vendor::factory()->create(['code' => 'INACTIVE-VENDOR']);
        $item = ProcurementItem::factory()->create();
        $historicalLink = VendorItem::factory()->create([
            'vendor_id' => $inactiveVendor->id,
            'item_id' => $item->id,
            'reference_price' => 75000,
        ]);

        $inactiveVendor->deactivate();

        $this->assertModelExists($inactiveVendor->refresh());
        $this->assertFalse($inactiveVendor->is_active);
        $this->assertModelExists($historicalLink->refresh());
        $this->assertTrue($historicalLink->vendor->is($inactiveVendor));
        $this->assertSame(
            ['ACTIVE-VENDOR'],
            Vendor::query()->availableForNewTransactions()->pluck('code')->all(),
        );
        $this->assertSame([], VendorItem::query()->availableForNewTransactions()->pluck('id')->all());
        $this->assertTrue($activeVendor->is_active);
    }
}
