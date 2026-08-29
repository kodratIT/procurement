<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\ProcurementMasterSeeder;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementCategoryConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_defaults_to_goods_type_with_disabled_dynamic_flags(): void
    {
        $category = ProcurementCategory::create([
            'code' => 'CAT-TEST',
            'name' => 'Kategori Test',
        ]);

        $this->assertSame('goods', $category->type);
        $this->assertFalse($category->requires_batch);
        $this->assertFalse($category->requires_vendor);
        $this->assertFalse($category->receiving);
        $this->assertFalse($category->invoice);
        $this->assertFalse($category->jamaah);
        $this->assertTrue($category->is_active);
    }

    public function test_category_can_be_configured_as_service_type(): void
    {
        $category = ProcurementCategory::create([
            'code' => 'SVC-TEST',
            'name' => 'Jasa Test',
            'type' => ProcurementCategory::TYPE_SERVICE,
            'requires_vendor' => true,
            'invoice' => true,
        ]);

        $this->assertSame('service', $category->type);
        $this->assertTrue($category->requires_vendor);
        $this->assertTrue($category->invoice);
        $this->assertFalse($category->requires_batch);
        $this->assertFalse($category->receiving);
        $this->assertFalse($category->jamaah);
    }

    public function test_category_can_be_configured_as_mixed_type_with_all_flags(): void
    {
        $category = ProcurementCategory::create([
            'code' => 'MIX-TEST',
            'name' => 'Campuran Test',
            'type' => ProcurementCategory::TYPE_MIXED,
            'requires_batch' => true,
            'requires_vendor' => true,
            'receiving' => true,
            'invoice' => true,
            'jamaah' => true,
        ]);

        $this->assertSame('mixed', $category->type);
        $this->assertTrue($category->requires_batch);
        $this->assertTrue($category->requires_vendor);
        $this->assertTrue($category->receiving);
        $this->assertTrue($category->invoice);
        $this->assertTrue($category->jamaah);
    }

    public function test_seeder_sets_meaningful_dynamic_configuration_for_categories(): void
    {
        $this->seed(ProcurementMasterSeeder::class);

        $pakaian = ProcurementCategory::where('code', 'PAKAIAN')->firstOrFail();
        $kesehatan = ProcurementCategory::where('code', 'KESEHATAN')->firstOrFail();
        $perjalanan = ProcurementCategory::where('code', 'PERJALANAN')->firstOrFail();

        $this->assertSame('goods', $pakaian->type);
        $this->assertTrue($pakaian->requires_batch);
        $this->assertTrue($pakaian->requires_vendor);
        $this->assertTrue($pakaian->receiving);
        $this->assertTrue($pakaian->invoice);
        $this->assertTrue($pakaian->jamaah);

        $this->assertSame('goods', $kesehatan->type);
        $this->assertFalse($kesehatan->requires_batch);
        $this->assertFalse($kesehatan->jamaah);
        $this->assertTrue($kesehatan->requires_vendor);

        $this->assertSame('mixed', $perjalanan->type);
        $this->assertTrue($perjalanan->requires_batch);
        $this->assertTrue($perjalanan->jamaah);
    }

    public function test_dynamic_flags_are_boolean_casts(): void
    {
        $category = ProcurementCategory::create([
            'code' => 'CAST-TEST',
            'name' => 'Cast Test',
            'requires_batch' => 1,
            'requires_vendor' => 0,
        ]);

        $fresh = $category->fresh();

        $this->assertTrue($fresh->requires_batch);
        $this->assertFalse($fresh->requires_vendor);
        $this->assertIsBool($fresh->requires_batch);
        $this->assertIsBool($fresh->requires_vendor);
        $this->assertIsBool($fresh->receiving);
    }

    public function test_category_type_constants_are_consistent(): void
    {
        $this->assertSame('goods', ProcurementCategory::TYPE_GOODS);
        $this->assertSame('service', ProcurementCategory::TYPE_SERVICE);
        $this->assertSame('mixed', ProcurementCategory::TYPE_MIXED);
        $this->assertArrayHasKey(ProcurementCategory::TYPE_GOODS, ProcurementCategory::TYPES);
        $this->assertArrayHasKey(ProcurementCategory::TYPE_SERVICE, ProcurementCategory::TYPES);
        $this->assertArrayHasKey(ProcurementCategory::TYPE_MIXED, ProcurementCategory::TYPES);
        $this->assertCount(3, ProcurementCategory::TYPES);
    }

    public function test_category_resource_page_renders(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $admin = User::factory()->create(['email' => 'kategori-admin@example.test']);
        $office = Office::factory()->create(['is_active' => true, 'disabled_at' => null]);
        UserAssignment::factory()->create([
            'user_id' => $admin->id,
            'office_id' => $office->id,
            'valid_from' => Carbon::yesterday(),
            'is_primary' => true,
            'is_active' => true,
        ]);
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/procurement-categories')
            ->assertSuccessful();
    }
}
