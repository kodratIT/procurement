<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\GoodsReceipt;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\DistributionService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DistributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_batch_distribution_and_calculates_batch_totals(): void
    {
        [$actor, $batch, $item] = $this->stockContext(5);
        $service = app(DistributionService::class);

        $distribution = $service->record($batch, [
            'distributed_at' => '2026-08-31',
            'receipt_mode' => Distribution::RECEIPT_MODE_BATCH,
            'lines' => [['procurement_item_id' => $item->id, 'quantity' => '2']],
        ], $actor);

        $this->assertModelExists($distribution);
        $this->assertDatabaseHas('distribution_items', [
            'distribution_id' => $distribution->id,
            'procurement_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $this->assertSame('2.00', $service->batchTotals($batch)[$item->id]);
        $this->assertSame('3.00', $service->availableQuantity($item, $batch));
    }

    public function test_rejects_over_distribution_without_creating_history(): void
    {
        [$actor, $batch, $item] = $this->stockContext(2);
        $service = app(DistributionService::class);

        $this->expectException(ValidationException::class);
        try {
            $service->record($batch, [
                'distributed_at' => '2026-08-31',
                'lines' => [['procurement_item_id' => $item->id, 'quantity' => '3']],
            ], $actor);
        } finally {
            $this->assertDatabaseCount('distributions', 0);
            $this->assertDatabaseCount('distribution_items', 0);
        }
    }

    public function test_received_availability_is_scoped_to_the_batch_office(): void
    {
        [$actor, $batch, $item] = $this->stockContext(4, includeOtherOffice: true);
        $service = app(DistributionService::class);

        $this->assertSame('4.00', $service->availableQuantity($item, $batch));
        $this->assertSame('13.00', $service->availableQuantity($item));
        $this->assertSame('4.00', $service->availability($batch)[$item->id]);
        $this->assertTrue($actor->is_active);
    }

    public function test_batch_totals_only_include_distribution_transactions_not_sample_shipments(): void
    {
        [$actor, $batch, $item] = $this->stockContext(5);
        $service = app(DistributionService::class);
        $service->record($batch, [
            'distributed_at' => '2026-08-31',
            'lines' => [['procurement_item_id' => $item->id, 'quantity' => '2']],
        ], $actor);

        $this->assertSame('2.00', $service->batchTotals($batch)[$item->id]);
        $this->assertSame('2.00', $service->distributedQuantities()[$item->id]);
    }

    private function stockContext(int $quantity, bool $includeOtherOffice = false): array
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
        $batch = UmrahBatch::factory()->create(['office_id' => $office->id]);
        $item = ProcurementItem::factory()->create();
        $this->stockReceipt($office, $item, $quantity, $actor);
        if ($includeOtherOffice) {
            $this->stockReceipt(Office::factory()->create(), $item, 9);
        }
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $batch, $item];
    }

    private function stockReceipt(Office $office, ProcurementItem $item, int $quantity, ?User $receiver = null): void
    {
        $request = PurchaseRequest::factory()->create(['office_id' => $office->id]);
        $requestItem = PurchaseRequestItem::factory()->for($request)->create([
            'procurement_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'unit_name' => 'pcs',
        ]);
        $order = PurchaseOrder::factory()->create([
            'purchase_request_id' => $request->id,
            'office_id' => $office->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $orderItem = PurchaseOrderItem::factory()->for($order)->create([
            'purchase_request_item_id' => $requestItem->id,
            'procurement_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => 100,
        ]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();
        $receipt = GoodsReceipt::factory()->create([
            'purchase_order_id' => $order->id,
            'office_id' => $office->id,
            'receiver_id' => $receiver?->id ?? User::factory(),
            'status' => GoodsReceipt::STATUS_COMPLETE,
        ]);
        $receipt->items()->create([
            'purchase_order_item_id' => $orderItem->id,
            'quantity' => $quantity,
        ]);
    }
}
