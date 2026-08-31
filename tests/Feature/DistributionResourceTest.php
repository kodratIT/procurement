<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Distributions\DistributionResource;
use App\Filament\Resources\Distributions\Pages\CreateDistribution;
use App\Filament\Resources\Distributions\Pages\ListDistributions;
use App\Filament\Resources\Distributions\Pages\ViewDistribution;
use App\Filament\Resources\Distributions\RelationManagers\PilgrimAllocationsRelationManager;
use App\Filament\Resources\Distributions\Schemas\DistributionForm;
use App\Models\Distribution;
use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\ProcurementItem;
use App\Models\Role;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DistributionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_resource_registers_pages_and_individual_relation_manager(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $this->assertContains(DistributionResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(Distribution::class, DistributionResource::getModel());
        $this->assertSame(ListDistributions::class, DistributionResource::getPages()['index']->getPage());
        $this->assertSame(CreateDistribution::class, DistributionResource::getPages()['create']->getPage());
        $this->assertSame(ViewDistribution::class, DistributionResource::getPages()['view']->getPage());
        $this->assertContains(PilgrimAllocationsRelationManager::class, DistributionResource::getRelations());
    }

    public function test_distribution_query_is_scoped_by_batch_office(): void
    {
        [$actor, $office] = $this->procurementContext();
        $otherOffice = Office::factory()->create();
        $inScope = Distribution::factory()->create([
            'umrah_batch_id' => UmrahBatch::factory()->forOffice($office)->open()->create()->id,
        ]);
        $outOfScope = Distribution::factory()->create([
            'umrah_batch_id' => UmrahBatch::factory()->forOffice($otherOffice)->open()->create()->id,
        ]);

        $this->actingAs($actor);
        app(AccessContextService::class)->setContext(UserAssignment::query()->where('user_id', $actor->id)->firstOrFail());

        $this->assertSame([$inScope->id], DistributionResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($outOfScope->id, DistributionResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_distribution_query_is_empty_without_authenticated_context(): void
    {
        Distribution::factory()->create();

        $this->assertSame([], DistributionResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_distribution_form_declares_explicit_receipt_modes_and_item_lines(): void
    {
        $components = DistributionResource::form(Schema::make())->getComponents();
        $names = collect($components)->map(fn ($component): string => $component->getName())->all();

        $this->assertContains('umrah_batch_id', $names);
        $this->assertContains('distributed_at', $names);
        $this->assertContains('receipt_mode', $names);
        $this->assertContains('status', $names);
        $this->assertContains('lines', $names);
    }

    public function test_distribution_item_options_include_remaining_received_availability(): void
    {
        $batch = UmrahBatch::factory()->open()->create();
        $item = ProcurementItem::factory()->create(['name' => 'Ihram set']);

        $method = new \ReflectionMethod(DistributionForm::class, 'itemOptions');
        $method->setAccessible(true);
        $options = $method->invoke(null, $batch->id);

        $this->assertArrayHasKey($item->id, $options);
        $this->assertStringContainsString('remaining received: 0.00', $options[$item->id]);
    }

    public function test_individual_distribution_exposes_batch_pilgrim_allocations(): void
    {
        $batch = UmrahBatch::factory()->open()->create();
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();
        $distribution = Distribution::factory()->individual()->create(['umrah_batch_id' => $batch->id]);
        $item = $distribution->items()->create([
            'procurement_item_id' => ProcurementItem::factory()->create()->id,
            'quantity' => '2.00',
        ]);
        $allocation = $item->pilgrimAllocations()->create([
            'pilgrim_id' => $pilgrim->id,
            'quantity' => '1.00',
        ]);

        $this->assertTrue($distribution->isIndividualMode());
        $this->assertSame([$allocation->id], $distribution->pilgrimAllocations()->pluck('id')->all());
        $relation = new \ReflectionClass(PilgrimAllocationsRelationManager::class);
        $this->assertSame('pilgrimAllocations', $relation->getDefaultProperties()['relationship']);
    }

    /** @return array{User, Office} */
    private function procurementContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        return [$actor, $office];
    }
}
