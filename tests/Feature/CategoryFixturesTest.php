<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use Database\Seeders\ProcurementMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_categories_cover_jamaah_supplies_hotel_and_transport(): void
    {
        $this->seed(ProcurementMasterSeeder::class);

        $jamaah = ProcurementCategory::query()->where('code', 'JAMAAH')->firstOrFail();
        $hotel = ProcurementCategory::query()->where('code', 'HOTEL')->firstOrFail();
        $transport = ProcurementCategory::query()->where('code', 'TRANSPORT')->firstOrFail();

        $this->assertSame(ProcurementCategoryType::GOODS, $jamaah->type);
        $this->assertTrue($jamaah->configuration()->requiresJamaah());
        $this->assertTrue($jamaah->configuration()->requiresBatch());

        $this->assertSame(ProcurementCategoryType::SERVICE, $hotel->type);
        $this->assertTrue($hotel->configuration()->requiresBatch());
        $this->assertFalse($hotel->configuration()->requiresJamaah());
        $this->assertSame('hotel-procurement', $hotel->configuration()->workflowReference);

        $this->assertSame(ProcurementCategoryType::SERVICE, $transport->type);
        $this->assertTrue($transport->configuration()->requiresVendor());
        $this->assertSame('purchase_request_transport', $transport->configuration()->numberTemplate);
    }
}
