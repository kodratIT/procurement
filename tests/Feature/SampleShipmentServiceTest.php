<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\SampleShipment;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\SampleShipmentService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SampleShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_office_scoped_shipment_with_items_and_origin_cost_link(): void
    {
        [$sender, $receiver, $order, $line, $senderOffice, $receiverOffice] = $this->shipmentContext();
        $shipment = app(SampleShipmentService::class)->create([
            'purchase_order_id' => $order->id,
            'sender_office_id' => $senderOffice->id,
            'receiver_office_id' => $receiverOffice->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'purpose' => 'Sizing and design approval sample',
            'requested_at' => '2026-08-31',
            'planned_ship_date' => '2026-09-01',
            'tracking_no' => 'TRACK-001',
            'shipping_cost' => '125000.50',
            'cost_center_id' => $order->cost_center_id,
            'lines' => [[
                'purchase_order_item_id' => $line->id,
                'procurement_item_id' => $line->procurement_item_id,
                'quantity' => '2',
                'condition' => 'good',
            ]],
        ], $sender);

        $this->assertSame(SampleShipment::STATUS_DRAFT, $shipment->statusValue());
        $this->assertSame($senderOffice->id, $shipment->office_id);
        $this->assertSame($senderOffice->id, $shipment->sender_office_id);
        $this->assertSame($receiverOffice->id, $shipment->receiver_office_id);
        $this->assertSame('125000.50', (string) $shipment->shipping_cost);
        $this->assertSame($order->id, $shipment->purchaseOrder->id);
        $this->assertSame($line->id, $shipment->items->first()->purchase_order_item_id);
        $this->assertSame('2.00', (string) $shipment->items->first()->quantity);
    }

    public function test_only_permitted_lifecycle_transitions_are_allowed(): void
    {
        [$sender, , $order, $line, $senderOffice, $receiverOffice] = $this->shipmentContext();
        $service = app(SampleShipmentService::class);
        $shipment = $service->create($this->shipmentData($order, $line, $senderOffice, $receiverOffice), $sender);

        $service->submit($shipment, $sender);
        $service->review($shipment, $sender);
        $service->approve($shipment, $sender);
        $service->ship($shipment, ['shipped_at' => '2026-09-01'], $sender);

        $this->assertSame(SampleShipment::STATUS_SHIPPED, $shipment->fresh()->statusValue());

        $this->expectException(ValidationException::class);
        $service->transition($shipment, SampleShipment::STATUS_COMPLETE, $sender);
    }

    public function test_receiver_scope_is_required_for_receiving_transitions(): void
    {
        [$sender, , $order, $line, $senderOffice, $receiverOffice] = $this->shipmentContext();
        $shipment = app(SampleShipmentService::class)->create($this->shipmentData($order, $line, $senderOffice, $receiverOffice), $sender);
        app(SampleShipmentService::class)->submit($shipment, $sender);
        app(SampleShipmentService::class)->review($shipment, $sender);
        app(SampleShipmentService::class)->approve($shipment, $sender);
        app(SampleShipmentService::class)->ship($shipment, [], $sender);

        $this->expectException(AuthorizationException::class);
        app(SampleShipmentService::class)->transition($shipment, SampleShipment::STATUS_RECEIVED, $sender);
    }

    /** @return array{User, User, PurchaseOrder, PurchaseOrderItem, Office, Office} */
    private function shipmentContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $senderOffice = Office::factory()->create();
        $receiverOffice = Office::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $role = Role::query()->where('name', 'Admin')->firstOrFail();
        $senderAssignment = UserAssignment::factory()->create(['user_id' => $sender->id, 'office_id' => $senderOffice->id, 'role_id' => $role->id, 'role' => $role->name, 'is_primary' => true]);
        UserAssignment::factory()->create(['user_id' => $receiver->id, 'office_id' => $receiverOffice->id, 'role_id' => $role->id, 'role' => $role->name, 'is_primary' => true]);
        $this->actingAs($sender);
        app(AccessContextService::class)->setContext($senderAssignment);

        $item = ProcurementItem::factory()->create();
        $request = PurchaseRequest::factory()->create(['office_id' => $senderOffice->id]);
        $requestItem = PurchaseRequestItem::factory()->create(['purchase_request_id' => $request->id, 'procurement_item_id' => $item->id, 'quantity' => 5]);
        $order = PurchaseOrder::factory()->create(['purchase_request_id' => $request->id, 'office_id' => $senderOffice->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $line = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'purchase_request_item_id' => $requestItem->id, 'procurement_item_id' => $item->id, 'quantity' => 5]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();

        return [$sender, $receiver, $order, $line, $senderOffice, $receiverOffice];
    }

    /** @return array<string, mixed> */
    private function shipmentData(PurchaseOrder $order, PurchaseOrderItem $line, Office $senderOffice, Office $receiverOffice): array
    {
        return [
            'purchase_order_id' => $order->id,
            'sender_office_id' => $senderOffice->id,
            'receiver_office_id' => $receiverOffice->id,
            'purpose' => 'Sample fitting',
            'lines' => [['purchase_order_item_id' => $line->id, 'procurement_item_id' => $line->procurement_item_id, 'quantity' => 1]],
        ];
    }
}
