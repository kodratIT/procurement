<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\RelationManagers\PaymentsRelationManager;
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

final class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_resource_registers_payment_history_and_scopes_records(): void
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

        $this->assertContains(PaymentsRelationManager::class, InvoiceResource::getRelations());
        $this->assertContains(InvoiceResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame([$inScope->id], InvoiceResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($outOfScope->id, InvoiceResource::getEloquentQuery()->pluck('id')->all());
    }
}
