<?php

namespace Tests\Feature;

use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\PurchaseRequestItem;
use Database\Seeders\ProcurementMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_schema_preserves_category_unit_variant_and_attribute_relationships(): void
    {
        $this->assertTrue(Schema::hasColumns('procurement_items', [
            'category_id',
            'unit_id',
            'code',
            'reference_price',
            'reference_currency',
            'specifications',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('procurement_variants', [
            'item_id',
            'variation_type',
            'attributes',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('purchase_request_items', [
            'procurement_item_id',
            'procurement_unit_id',
            'procurement_variant_id',
            'variant_name',
            'variant_value',
        ]));

        $category = ProcurementCategory::factory()->create();
        $unit = ProcurementUnit::factory()->create();
        $item = ProcurementItem::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'specifications' => ['Bahan' => 'Katun'],
        ]);
        $variant = ProcurementVariant::factory()->create([
            'item_id' => $item->id,
            'variation_type' => ProcurementVariant::TYPE_WARNA,
            'attributes' => ['hex' => '#ffffff'],
        ]);

        $this->assertTrue($item->category->is($category));
        $this->assertTrue($item->unit->is($unit));
        $this->assertTrue($item->variants->contains($variant));
        $this->assertTrue($variant->item->is($item));
        $this->assertSame(['Bahan' => 'Katun'], $item->specificationList());
        $this->assertSame(['hex' => '#ffffff'], $variant->attributes);
    }

    public function test_new_transaction_lines_reject_inactive_items(): void
    {
        $inactiveItem = ProcurementItem::factory()->create(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        PurchaseRequestItem::factory()->make(['procurement_item_id' => $inactiveItem->id])->save();
    }

    public function test_new_transaction_lines_reject_units_from_another_item(): void
    {
        $item = ProcurementItem::factory()->create();
        $otherUnit = ProcurementUnit::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        PurchaseRequestItem::factory()->make([
            'procurement_item_id' => $item->id,
            'procurement_unit_id' => $otherUnit->id,
        ])->save();
    }

    public function test_new_transaction_lines_reject_inactive_variants(): void
    {
        $item = ProcurementItem::factory()->create();
        $variant = ProcurementVariant::factory()->create(['item_id' => $item->id]);
        $variant->deactivate();

        $this->expectException(\InvalidArgumentException::class);
        PurchaseRequestItem::factory()->make([
            'procurement_item_id' => $item->id,
            'procurement_unit_id' => $item->unit_id,
            'procurement_variant_id' => $variant->id,
        ])->save();
    }

    public function test_historical_lines_remain_readable_after_catalog_deactivation(): void
    {
        $item = ProcurementItem::factory()->create();
        $variant = ProcurementVariant::factory()->create(['item_id' => $item->id]);
        $line = PurchaseRequestItem::factory()->create([
            'procurement_item_id' => $item->id,
            'procurement_unit_id' => $item->unit_id,
            'procurement_variant_id' => $variant->id,
        ]);

        $item->deactivate();
        $variant->deactivate();

        $line = $line->fresh();
        $this->assertTrue($line->procurementItem->is($item));
        $this->assertTrue($line->procurementVariant->is($variant));
        $this->assertSame($variant->name, $line->variant_name);
    }

    public function test_active_scopes_exclude_deactivated_catalog_records(): void
    {
        $active = ProcurementItem::factory()->create(['code' => 'ACTIVE-ITEM']);
        ProcurementItem::factory()->create(['code' => 'INACTIVE-ITEM', 'is_active' => false]);
        $activeVariant = ProcurementVariant::factory()->create(['item_id' => $active->id, 'code' => 'ACTIVE']);
        ProcurementVariant::factory()->create(['item_id' => $active->id, 'code' => 'INACTIVE', 'is_active' => false]);

        $this->assertSame(['ACTIVE-ITEM'], ProcurementItem::query()->active()->pluck('code')->all());
        $this->assertSame(['ACTIVE'], ProcurementVariant::query()->active()->pluck('code')->all());
        $this->assertTrue($activeVariant->is_active);
    }

    public function test_master_seeder_is_idempotent_for_catalog_records(): void
    {
        $this->seed(ProcurementMasterSeeder::class);
        $this->seed(ProcurementMasterSeeder::class);

        $this->assertSame(13, ProcurementVariant::query()->count());
        $this->assertSame(7, ProcurementCategory::query()->count());
    }
}
