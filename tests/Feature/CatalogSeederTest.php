<?php

namespace Tests\Feature;

use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use Database\Seeders\ProcurementMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_contains_required_jamaah_supply_catalog(): void
    {
        $this->seed(ProcurementMasterSeeder::class);

        $this->assertSame([
            'ID-CARD',
            'KAIN-IHRAM',
            'KOPER',
            'LABEL-KOPER',
            'MUKENA',
            'SERAGAM',
            'TAS-PASPOR',
        ], ProcurementItem::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['BOX', 'PACK', 'PCS', 'SET'], ProcurementUnit::query()->orderBy('code')->pluck('code')->all());

        $seragam = ProcurementItem::query()->where('code', 'SERAGAM')->firstOrFail();
        $this->assertSame('IDR', $seragam->reference_currency);
        $this->assertSame(4, $seragam->variants()->where('variation_type', ProcurementVariant::TYPE_UKURAN)->count());

        $mukena = ProcurementItem::query()->where('code', 'MUKENA')->firstOrFail();
        $this->assertSame(3, $mukena->variants()->where('variation_type', ProcurementVariant::TYPE_WARNA)->count());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(ProcurementMasterSeeder::class);
        $this->seed(ProcurementMasterSeeder::class);

        $this->assertSame(7, ProcurementItem::query()->count());
        $this->assertSame(13, ProcurementVariant::query()->count());
        $this->assertSame(4, ProcurementUnit::query()->count());
    }
}
