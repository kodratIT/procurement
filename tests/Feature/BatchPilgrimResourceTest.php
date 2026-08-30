<?php

namespace Tests\Feature;

use App\Filament\Imports\PilgrimImporter;
use App\Filament\Imports\UmrahBatchImporter;
use App\Filament\Resources\Pilgrims\PilgrimResource;
use App\Filament\Resources\UmrahBatches\UmrahBatchResource;
use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\Role;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPilgrimResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_and_pilgrim_resources_only_query_assigned_offices(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $role = Role::query()->where('name', 'Operasional')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $user->getKey(),
            'office_id' => $officeA->getKey(),
            'role_id' => $role->getKey(),
            'is_primary' => true,
        ]);
        $batchA = UmrahBatch::factory()->forOffice($officeA)->open()->create(['code' => 'UMR-A']);
        $batchB = UmrahBatch::factory()->forOffice($officeB)->open()->create(['code' => 'UMR-B']);
        $pilgrimA = Pilgrim::factory()->forBatch($batchA)->create();
        $pilgrimB = Pilgrim::factory()->forBatch($batchB)->create();

        $this->actingAs($user);

        $this->assertTrue(UmrahBatchResource::getEloquentQuery()->pluck('id')->contains($batchA->getKey()));
        $this->assertFalse(UmrahBatchResource::getEloquentQuery()->pluck('id')->contains($batchB->getKey()));
        $this->assertTrue(PilgrimResource::getEloquentQuery()->pluck('id')->contains($pilgrimA->getKey()));
        $this->assertFalse(PilgrimResource::getEloquentQuery()->pluck('id')->contains($pilgrimB->getKey()));
    }

    public function test_importers_define_required_server_side_columns(): void
    {
        $batchColumns = collect(UmrahBatchImporter::getColumns())->keyBy(fn ($column): string => $column->getName());
        $pilgrimColumns = collect(PilgrimImporter::getColumns())->keyBy(fn ($column): string => $column->getName());

        $this->assertSame(['code', 'name', 'departure_date', 'return_date', 'capacity', 'pilgrim_count', 'status', 'is_active'], $batchColumns->keys()->all());
        $this->assertSame(['umrah_batch_id', 'name', 'passport_no', 'phone', 'status', 'is_active'], $pilgrimColumns->keys()->all());
    }
}
