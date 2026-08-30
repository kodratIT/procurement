<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorItem;
use Database\Seeders\ProcurementMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_seeder_creates_repeatable_overlapping_and_distinct_vendor_items(): void
    {
        $this->seed(ProcurementMasterSeeder::class);
        $this->seed(ProcurementMasterSeeder::class);

        $this->assertSame(2, Vendor::query()->count());
        $this->assertSame(6, VendorItem::query()->count());

        $firstVendorItems = Vendor::query()
            ->where('code', 'VND-UMROH-001')
            ->firstOrFail()
            ->items()
            ->with('item')
            ->get()
            ->pluck('item.code')
            ->sort()
            ->values()
            ->all();
        $secondVendorItems = Vendor::query()
            ->where('code', 'VND-UMROH-002')
            ->firstOrFail()
            ->items()
            ->with('item')
            ->get()
            ->pluck('item.code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['KOPER', 'MUKENA', 'SERAGAM'], $firstVendorItems);
        $this->assertSame(['KAIN-IHRAM', 'KOPER', 'TAS-PASPOR'], $secondVendorItems);
        $this->assertSame(['KOPER'], array_values(array_intersect($firstVendorItems, $secondVendorItems)));
        $this->assertSame(['MUKENA', 'SERAGAM'], array_values(array_diff($firstVendorItems, $secondVendorItems)));
        $this->assertSame(['KAIN-IHRAM', 'TAS-PASPOR'], array_values(array_diff($secondVendorItems, $firstVendorItems)));
        $this->assertSame(6, VendorItem::query()->where('currency', 'IDR')->count());
    }
}
