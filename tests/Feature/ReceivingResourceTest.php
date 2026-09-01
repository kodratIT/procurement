<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\RelationManagers\GoodsReceiptsRelationManager;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReceivingResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_ui_registers_receipts_and_permission_scoped_panel_access(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        $this->assertContains(PurchaseOrderResource::class, Filament::getPanel('admin')->getResources());
        $this->assertContains(GoodsReceiptsRelationManager::class, PurchaseOrderResource::getRelations());
    }

    public function test_receipts_for_another_office_are_not_visible_through_purchase_order_query(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        PurchaseOrder::factory()->create(['office_id' => $otherOffice->id]);
        $inScope = PurchaseOrder::factory()->create(['office_id' => $office->id]);
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        $this->assertSame([$inScope->id], PurchaseOrderResource::getEloquentQuery()->pluck('id')->all());
    }
}
