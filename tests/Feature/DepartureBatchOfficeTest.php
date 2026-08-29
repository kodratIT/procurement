<?php

namespace Tests\Feature;

use App\Models\DepartureBatch;
use App\Models\Office;
use App\Models\ProcurementItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DepartureBatchOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_departure_batches_have_office_and_pax_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('departure_batches', ['office_id', 'pax_count']));
    }

    public function test_batch_belongs_to_office(): void
    {
        $office = Office::factory()->create(['name' => 'Kantor Pusat Jakarta']);
        $batch = DepartureBatch::factory()->create(['office_id' => $office->id]);

        $this->assertTrue($batch->office->is($office));
        $this->assertSame('Kantor Pusat Jakarta', $batch->office->name);
    }

    public function test_pax_count_must_be_at_least_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pax_count/i');

        DepartureBatch::factory()->create(['pax_count' => 0]);
    }

    public function test_seeder_creates_two_offices_and_full_pilgrimage_gear(): void
    {
        $this->seed();

        $this->assertSame(2, Office::count());
        $this->assertDatabaseHas('offices', ['code' => 'JKT-01', 'name' => 'Kantor Pusat Jakarta']);
        $this->assertDatabaseHas('offices', ['code' => 'SBY-01', 'name' => 'Kantor Cabang Surabaya']);

        $this->assertDatabaseHas('procurement_items', ['code' => 'SERAGAM']);
        $this->assertDatabaseHas('procurement_items', ['code' => 'ATRIBUT']);
        $this->assertSame(8, ProcurementItem::count());

        $this->assertSame(3, DepartureBatch::count());
        $this->assertDatabaseHas('departure_batches', ['code' => 'UMR-2026-03', 'pax_count' => 28]);

        $surabaya = Office::where('code', 'SBY-01')->firstOrFail();
        $this->assertDatabaseHas('departure_batches', ['code' => 'UMR-2026-03', 'office_id' => $surabaya->id]);
    }
}
