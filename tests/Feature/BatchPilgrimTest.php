<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\UmrahBatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class BatchPilgrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_office_owned_batch_and_linked_pilgrim(): void
    {
        $office = Office::factory()->create();
        $batch = UmrahBatch::factory()->forOffice($office)->create([
            'code' => 'UMR-001',
            'name' => 'Januari 2027',
            'departure_date' => '2027-01-10',
            'pilgrim_count' => 1,
        ]);

        $pilgrim = Pilgrim::factory()->forBatch($batch)->create([
            'name' => 'Aisyah',
            'passport_no' => ' p12345678 ',
        ]);

        $this->assertSame($office->getKey(), $batch->office_id);
        $this->assertSame($batch->getKey(), $pilgrim->umrah_batch_id);
        $this->assertSame('P12345678', $pilgrim->passport_no);
        $this->assertTrue($batch->pilgrims->contains($pilgrim));
        $this->assertTrue($pilgrim->batch->is($batch));
    }

    public function test_prevents_duplicate_passport_within_a_batch(): void
    {
        $batch = UmrahBatch::factory()->create();
        Pilgrim::factory()->forBatch($batch)->create(['passport_no' => 'P123']);

        $this->expectException(QueryException::class);
        Pilgrim::factory()->forBatch($batch)->create(['passport_no' => 'p123']);
    }

    public function test_allows_the_same_passport_in_another_batch(): void
    {
        $office = Office::factory()->create();
        $firstBatch = UmrahBatch::factory()->forOffice($office)->create(['code' => 'UMR-001']);
        $secondBatch = UmrahBatch::factory()->forOffice($office)->create(['code' => 'UMR-002']);
        Pilgrim::factory()->forBatch($firstBatch)->create(['passport_no' => 'P123']);

        $pilgrim = Pilgrim::factory()->forBatch($secondBatch)->create(['passport_no' => 'p123']);

        $this->assertModelExists($pilgrim);
    }

    public function test_deactivation_preserves_history_and_delete_is_refused(): void
    {
        $this->travelTo(Carbon::parse('2026-08-31 10:00:00'));
        $batch = UmrahBatch::factory()->create();
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();

        $batch->deactivate();
        $pilgrim->deactivate();

        $this->assertFalse($batch->fresh()->is_active);
        $this->assertNotNull($batch->fresh()->disabled_at);
        $this->assertFalse($pilgrim->fresh()->is_active);
        $this->assertNotNull($pilgrim->fresh()->disabled_at);
        $this->assertModelExists($batch);
        $this->assertModelExists($pilgrim);

        $this->expectException(\LogicException::class);
        $pilgrim->delete();
    }

    public function test_rejects_a_pilgrim_from_another_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $batch = UmrahBatch::factory()->forOffice($officeA)->create();

        $this->expectException(InvalidArgumentException::class);
        Pilgrim::factory()->forBatch($batch)->create(['office_id' => $officeB->getKey()]);
    }
}
