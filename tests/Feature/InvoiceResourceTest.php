<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_registers_entry_list_and_detail_pages(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $this->assertContains(InvoiceResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(Invoice::class, InvoiceResource::getModel());
        $this->assertSame(ListInvoices::class, InvoiceResource::getPages()['index']->getPage());
        $this->assertSame(CreateInvoice::class, InvoiceResource::getPages()['create']->getPage());
        $this->assertSame(ViewInvoice::class, InvoiceResource::getPages()['view']->getPage());
    }

    public function test_resource_query_is_limited_to_the_active_assignment_scope(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $inScope = Invoice::factory()->create(['office_id' => $office->id]);
        $outOfScope = Invoice::factory()->create(['office_id' => $otherOffice->id]);
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        $this->assertSame([$inScope->id], InvoiceResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($outOfScope->id, InvoiceResource::getEloquentQuery()->pluck('id')->all());
    }
}
