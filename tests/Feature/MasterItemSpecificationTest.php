<?php

namespace Tests\Feature;

use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use Database\Seeders\ProcurementMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterItemSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_tables_have_reference_price_currency_and_specification_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('procurement_items', [
            'reference_price', 'reference_currency', 'specifications',
        ]));
        $this->assertTrue(Schema::hasColumns('procurement_variants', ['variation_type']));
    }

    public function test_item_belongs_to_category_and_unit_and_has_variants(): void
    {
        $category = ProcurementCategory::factory()->create(['name' => 'Pakaian Jamaah']);
        $unit = ProcurementUnit::factory()->create(['name' => 'Set']);
        $item = ProcurementItem::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);
        $variant = ProcurementVariant::factory()->create(['item_id' => $item->id]);

        $this->assertTrue($item->category->is($category));
        $this->assertTrue($item->unit->is($unit));
        $this->assertTrue($item->variants->contains($variant));
        $this->assertTrue($variant->item->is($item));
    }

    public function test_item_stores_reference_price_currency_and_specifications(): void
    {
        $item = ProcurementItem::factory()->create([
            'reference_price' => 175000.50,
            'reference_currency' => 'IDR',
            'specifications' => ['Bahan' => 'Katun 100%', 'Panjang' => '2,5 m'],
        ]);

        $this->assertSame('175000.50', $item->reference_price);
        $this->assertSame('IDR', $item->reference_currency);
        $this->assertSame(['Bahan' => 'Katun 100%', 'Panjang' => '2,5 m'], $item->specifications);
        $this->assertSame(['Bahan' => 'Katun 100%', 'Panjang' => '2,5 m'], $item->specificationList());
        $this->assertSame('175.001 IDR', $item->formattedReferencePrice());
    }

    public function test_variant_stores_variation_type(): void
    {
        $item = ProcurementItem::factory()->create();
        $variant = ProcurementVariant::factory()->create([
            'item_id' => $item->id,
            'variation_type' => ProcurementVariant::TYPE_WARNA,
            'code' => 'NAVY',
            'name' => 'Warna Navy',
            'value' => 'Navy',
        ]);

        $this->assertSame('warna', $variant->variation_type);
        $this->assertSame(['ukuran', 'warna', 'bahan'], ProcurementVariant::TYPES);
    }

    public function test_active_scope_only_returns_active_items(): void
    {
        ProcurementItem::factory()->create(['code' => 'AKTIF-1', 'is_active' => true]);
        ProcurementItem::factory()->create(['code' => 'AKTIF-2', 'is_active' => true]);
        ProcurementItem::factory()->create(['code' => 'NONAKTIF', 'is_active' => false]);

        $codes = ProcurementItem::query()->active()->pluck('code')->all();

        $this->assertSame(['AKTIF-1', 'AKTIF-2'], $codes);
    }

    public function test_seeder_creates_items_with_reference_price_specs_and_variant_types(): void
    {
        $this->seed();

        $this->assertSame(6, ProcurementItem::count());
        $this->assertSame(4, ProcurementUnit::count());
        $this->assertSame(4, ProcurementCategory::count());

        $kain = ProcurementItem::query()->where('code', 'KAIN-IHRAM')->first();
        $this->assertNotNull($kain);
        $this->assertSame('175000.00', $kain->reference_price);
        $this->assertSame('IDR', $kain->reference_currency);
        $this->assertSame('Katun 100%', $kain->specifications['Bahan']);

        $this->assertSame(4, $kain->variants()->count());
        $this->assertSame(4, $kain->variants()->where('variation_type', 'ukuran')->count());

        $mukena = ProcurementItem::query()->where('code', 'MUKENA')->first();
        $this->assertNotNull($mukena);
        $this->assertSame(3, $mukena->variants()->count());
        $this->assertSame(3, $mukena->variants()->where('variation_type', 'warna')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ProcurementMasterSeeder::class);
        $this->seed(ProcurementMasterSeeder::class);

        $this->assertSame(6, ProcurementItem::count());
        $this->assertSame(7, ProcurementVariant::count());
    }
}
