<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\ReceivingService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class GoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_full_receipts_calculate_cumulative_status_and_remaining_quantity(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(5);
        $service = app(ReceivingService::class);

        $partial = $service->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '2']],
        ], $actor);
        $complete = $service->record($order, [
            'received_date' => '2026-09-01',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '3']],
        ], $actor);

        $this->assertSame(GoodsReceipt::STATUS_PARTIALLY_RECEIVED, $partial->status);
        $this->assertSame(GoodsReceipt::STATUS_COMPLETE, $complete->status);
        $this->assertSame(GoodsReceipt::STATUS_COMPLETE, $service->status($order));
        $this->assertSame('5.00', $service->receivedQuantities($order)[$line->id]);
        $this->assertSame('0.00', $service->remainingQuantities($order)[$line->id]);
        $this->assertSame($actor->id, $partial->receiver_id);
    }

    public function test_status_requires_every_purchase_order_line_for_completion(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(2);
        $order->forceFill(['status' => PurchaseOrder::STATUS_DRAFT])->saveQuietly();
        $secondRequestItem = $order->purchaseRequest->items()->create([
            'item_name' => 'Shoes',
            'quantity' => 3,
            'unit_name' => 'pairs',
            'unit_price' => 150,
        ]);
        $secondLine = $order->items()->create([
            'purchase_request_item_id' => $secondRequestItem->id,
            'item_name' => 'Shoes',
            'quantity' => 3,
            'unit_name' => 'pairs',
            'unit_price' => 150,
        ]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();
        $service = app(ReceivingService::class);

        $partial = $service->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '2']],
        ], $actor);
        $this->assertSame(GoodsReceipt::STATUS_PARTIALLY_RECEIVED, $partial->status);
        $this->assertSame(GoodsReceipt::STATUS_PARTIALLY_RECEIVED, $service->status($order));

        $complete = $service->record($order, [
            'received_date' => '2026-09-01',
            'lines' => [['purchase_order_item_id' => $secondLine->id, 'quantity' => '3']],
        ], $actor);

        $this->assertSame(GoodsReceipt::STATUS_COMPLETE, $complete->status);
        $this->assertSame(GoodsReceipt::STATUS_COMPLETE, $service->status($order));
        $this->assertSame('2.00', $service->receivedQuantities($order)[$line->id]);
        $this->assertSame('3.00', $service->receivedQuantities($order)[$secondLine->id]);
    }

    public function test_receipts_are_rejected_for_non_receivable_purchase_orders(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1, PurchaseOrder::STATUS_DRAFT);

        try {
            app(ReceivingService::class)->record($order, [
                'received_date' => '2026-08-31',
                'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
            ], $actor);
            $this->fail('A draft purchase order must not accept receipts.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Only an approved or issued purchase order can receive goods or services.'],
                $exception->errors()['purchase_order'],
            );
        }

        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('goods_receipt_items', 0);
    }

    public function test_receipt_authorization_uses_the_locked_purchase_order_scope(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1);
        $staleOrder = $order->fresh(['items']);
        $otherOffice = Office::factory()->create();
        $order->forceFill(['office_id' => $otherOffice->id])->saveQuietly();

        $this->expectException(AuthorizationException::class);
        app(ReceivingService::class)->record($staleOrder, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);
    }

    public function test_receiver_must_match_the_purchase_order_organizational_scope(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1);
        $branch = Branch::factory()->create(['office_id' => $order->office_id]);
        $otherBranch = Branch::factory()->create(['office_id' => $order->office_id]);
        $order->forceFill(['branch_id' => $branch->id])->saveQuietly();
        $receiver = User::factory()->create();
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        UserAssignment::factory()->create([
            'user_id' => $receiver->id,
            'office_id' => $order->office_id,
            'branch_id' => $otherBranch->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        try {
            app(ReceivingService::class)->record($order, [
                'received_date' => '2026-08-31',
                'receiver_id' => $receiver->id,
                'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
            ], $actor);
            $this->fail('A receiver outside the purchase order scope must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The receiver must have an active assignment in the purchase order scope.'],
                $exception->errors()['receiver_id'],
            );
        }

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_receipt_line_ids_must_be_integers(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1);

        try {
            app(ReceivingService::class)->record($order, [
                'received_date' => '2026-08-31',
                'lines' => [['purchase_order_item_id' => $line->id.'.5', 'quantity' => 1]],
            ], $actor);
            $this->fail('A fractional line id must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Each receipt line must identify a purchase order item.'],
                $exception->errors()['lines.0.purchase_order_item_id'],
            );
        }

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_purchase_order_without_receipts_is_not_received(): void
    {
        [, $order] = $this->receivableOrder(5);

        $this->assertSame(GoodsReceipt::STATUS_NOT_RECEIVED, app(ReceivingService::class)->status($order));
    }

    public function test_receipt_mutation_is_rejected_outside_active_office_scope(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(5);
        $order->forceFill(['office_id' => Office::factory()->create()->id])->saveQuietly();

        $this->expectException(AuthorizationException::class);
        app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);
    }

    public function test_over_receipt_is_rejected_without_mutating_receipt_history(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(5);
        $service = app(ReceivingService::class);
        $service->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '4']],
        ], $actor);

        try {
            $service->record($order, [
                'received_date' => '2026-09-01',
                'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '2']],
            ], $actor);
            $this->fail('Cumulative over-receipt must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.quantity', $exception->errors());
        }

        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('goods_receipt_items', 1);
        $this->assertSame(GoodsReceipt::STATUS_PARTIALLY_RECEIVED, $service->status($order));
    }

    public function test_receipt_is_allowed_for_an_issued_purchase_order(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1, PurchaseOrder::STATUS_ISSUED);

        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '1']],
        ], $actor);

        $this->assertSame(GoodsReceipt::STATUS_COMPLETE, $receipt->status);
    }

    public function test_correction_creates_replacement_and_preserves_original_history(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(5, PurchaseOrder::STATUS_APPROVED, 'Admin');
        $service = app(ReceivingService::class);
        $original = $service->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '2']],
        ], $actor);

        $correction = $service->correct($original, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => '4']],
        ], 'Corrected signed delivery note quantity.', $actor);

        $this->assertNotSame($original->id, $correction->id);
        $this->assertSame($original->id, $correction->correction_of_id);
        $this->assertSame('Corrected signed delivery note quantity.', $correction->correction_reason);
        $this->assertSame('4.00', app(ReceivingService::class)->receivedQuantities($order)[$line->id]);
        $this->assertDatabaseHas('goods_receipts', ['id' => $original->id, 'status' => GoodsReceipt::STATUS_PARTIALLY_RECEIVED]);
    }

    public function test_receipt_history_cannot_be_updated_or_deleted(): void
    {
        [$actor, $order, $line] = $this->receivableOrder(1);
        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);

        $this->expectException(\LogicException::class);
        $receipt->update(['notes' => 'Tampered']);
    }

    /** @return array{User, PurchaseOrder, PurchaseOrderItem} */
    private function receivableOrder(int $quantity, string $status = PurchaseOrder::STATUS_APPROVED, string $roleName = 'Pengadaan'): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $request = PurchaseRequest::factory()->create(['office_id' => $office->id]);
        $requestItem = $request->items()->create([
            'item_name' => 'Uniform',
            'quantity' => $quantity,
            'unit_price' => 100,
            'unit_name' => 'pcs',
        ]);
        $order = PurchaseOrder::factory()->create([
            'purchase_request_id' => $request->id,
            'office_id' => $office->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $line = $order->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'item_name' => 'Uniform',
            'quantity' => $quantity,
            'unit_name' => 'pcs',
            'unit_price' => 100,
        ]);
        $order->forceFill(['status' => $status])->saveQuietly();
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $order->fresh(['items']), $line->fresh()];
    }
}
