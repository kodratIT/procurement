<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\EditQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_registers_entry_edit_and_comparison_pages(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $this->assertContains(QuotationResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(Quotation::class, QuotationResource::getModel());
        $this->assertSame(ListQuotations::class, QuotationResource::getPages()['index']->getPage());
        $this->assertSame(CreateQuotation::class, QuotationResource::getPages()['create']->getPage());
        $this->assertSame(EditQuotation::class, QuotationResource::getPages()['edit']->getPage());
    }

    public function test_resource_query_only_returns_quotations_for_the_active_procurement_scope(): void
    {
        [$reviewer, $office] = $this->procurementContext();
        $otherOffice = Office::factory()->create();
        $inScopeRequest = PurchaseRequest::factory()->create(['office_id' => $office->id]);
        $outOfScopeRequest = PurchaseRequest::factory()->create(['office_id' => $otherOffice->id]);
        $inScope = Quotation::factory()->for($inScopeRequest)->create();
        $outOfScope = Quotation::factory()->for($outOfScopeRequest)->create();

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext(UserAssignment::query()->where('user_id', $reviewer->id)->firstOrFail());

        $ids = QuotationResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$inScope->id], $ids);
        $this->assertNotContains($outOfScope->id, $ids);
    }

    public function test_resource_requires_procurement_update_scope(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $viewer = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Viewer')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $viewer->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($viewer);

        $this->assertFalse(QuotationResource::canViewAny());
        $this->assertSame([], QuotationResource::getEloquentQuery()->pluck('id')->all());
    }

    /** @return array{User, Office} */
    private function procurementContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $reviewer->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        return [$reviewer, $office];
    }
}
