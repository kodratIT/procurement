<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\ProcurementCategory;
use App\Models\UmrahBatch;
use App\Services\CategoryRelationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryRelationGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_category_requirements_once_and_exposes_typed_flags(): void
    {
        $category = ProcurementCategory::factory()->create([
            'requires_batch' => true,
            'requires_jamaah' => true,
        ]);
        $guard = CategoryRelationGuard::forCategory($category);

        $configuration = $guard->configuration();

        $this->assertSame($configuration, $guard->requirements());
        $this->assertTrue($guard->requiresBatch());
        $this->assertTrue($guard->requiresPilgrim());
        $this->assertTrue($guard->requiresJamaah());
        $this->assertSame('required', $guard->rulesForRelations()['umrah_batch_id'][0]);
        $this->assertSame('required', $guard->rulesForRelations()['pilgrim_id'][0]);
    }

    public function test_rejects_missing_required_relations(): void
    {
        $category = ProcurementCategory::factory()->create([
            'requires_batch' => true,
            'requires_jamaah' => true,
        ]);

        $this->expectException(ValidationException::class);
        CategoryRelationGuard::forCategory($category)->validate([]);
    }

    public function test_accepts_matching_active_relations_in_the_same_office(): void
    {
        $office = Office::factory()->create();
        $category = ProcurementCategory::factory()->create([
            'requires_batch' => true,
            'requires_jamaah' => true,
        ]);
        $batch = UmrahBatch::factory()->forOffice($office)->open()->create();
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();

        $relations = CategoryRelationGuard::forCategory($category)->validate([
            'umrah_batch_id' => $batch->getKey(),
            'pilgrim_id' => $pilgrim->getKey(),
        ], $office->getKey());

        $this->assertSame($batch->getKey(), $relations['umrah_batch_id']);
        $this->assertSame($pilgrim->getKey(), $relations['pilgrim_id']);
    }

    public function test_rejects_relations_from_another_office_or_batch(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $category = ProcurementCategory::factory()->create([
            'requires_batch' => true,
            'requires_jamaah' => true,
        ]);
        $batchA = UmrahBatch::factory()->forOffice($officeA)->open()->create();
        $batchB = UmrahBatch::factory()->forOffice($officeB)->open()->create();
        $pilgrimB = Pilgrim::factory()->forBatch($batchB)->create();

        $this->expectException(ValidationException::class);
        CategoryRelationGuard::forCategory($category)->validate([
            'umrah_batch_id' => $batchA->getKey(),
            'pilgrim_id' => $pilgrimB->getKey(),
        ], $officeA->getKey());
    }
}
