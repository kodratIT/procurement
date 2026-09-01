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
use App\Models\SampleShipmentReceipt;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\SampleShipmentReceiptService;
use App\Services\SampleShipmentService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SampleShipmentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_confirmation_requires_quantity_condition_date_and_both_evidence_types(): void
    {
        [$shipment, $receiver, $receiverAssignment] = $this->shippedShipment();
        $this->actingAs($receiver);
        app(AccessContextService::class)->setContext($receiverAssignment);

        try {
            app(SampleShipmentReceiptService::class)->confirm($shipment, ['quantity' => 1], $receiver);
            $this->fail('Missing receipt fields must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('condition', $exception->errors());
            $this->assertArrayHasKey('received_at', $exception->errors());
            $this->assertArrayHasKey('evidence', $exception->errors());
        }
        $this->assertDatabaseCount('sample_shipment_receipts', 0);
    }

    public function test_confirmation_persists_condition_quantity_disposition_and_evidence(): void
    {
        Storage::fake('private');
        [$shipment, $receiver, $receiverAssignment] = $this->shippedShipment();
        $this->actingAs($receiver);
        app(AccessContextService::class)->setContext($receiverAssignment);

        $receipt = app(SampleShipmentReceiptService::class)->confirm($shipment, [
            'quantity' => '2',
            'condition' => 'fair',
            'received_at' => '2026-09-03',
            'disposition' => SampleShipmentReceipt::DISPOSITION_STORED,
            'evidence' => [
                ['type' => 'photo', 'file' => UploadedFile::fake()->image('delivery.jpg')],
                ['type' => 'signature', 'file' => UploadedFile::fake()->image('signature.png')],
            ],
        ], $receiver);

        $this->assertSame('2.00', (string) $receipt->quantity);
        $this->assertSame('fair', $receipt->conditionValue());
        $this->assertSame(SampleShipmentReceipt::DISPOSITION_STORED, $receipt->disposition);
        $this->assertCount(2, $receipt->attachments);
        $this->assertSame(SampleShipment::STATUS_CONFIRMED, $shipment->fresh()->statusValue());
        $this->assertSame('stored', $shipment->fresh()->ownership);
    }

    /** @return array{SampleShipment, User, UserAssignment} */
    private function shippedShipment(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $senderOffice = Office::factory()->create();
        $receiverOffice = Office::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $role = Role::query()->where('name', 'Admin')->firstOrFail();
        $senderAssignment = UserAssignment::factory()->create(['user_id' => $sender->id, 'office_id' => $senderOffice->id, 'role_id' => $role->id, 'role' => $role->name, 'is_primary' => true]);
        $receiverAssignment = UserAssignment::factory()->create(['user_id' => $receiver->id, 'office_id' => $receiverOffice->id, 'role_id' => $role->id, 'role' => $role->name, 'is_primary' => true]);
        $this->actingAs($sender);
        app(AccessContextService::class)->setContext($senderAssignment);

        $item = ProcurementItem::factory()->create();
        $request = PurchaseRequest::factory()->create(['office_id' => $senderOffice->id]);
        $requestItem = PurchaseRequestItem::factory()->create(['purchase_request_id' => $request->id, 'procurement_item_id' => $item->id, 'quantity' => 2]);
        $order = PurchaseOrder::factory()->create(['purchase_request_id' => $request->id, 'office_id' => $senderOffice->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $line = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'purchase_request_item_id' => $requestItem->id, 'procurement_item_id' => $item->id, 'quantity' => 2]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();
        $shipment = app(SampleShipmentService::class)->create([
            'purchase_order_id' => $order->id,
            'sender_office_id' => $senderOffice->id,
            'receiver_office_id' => $receiverOffice->id,
            'purpose' => 'Fitting sample',
            'lines' => [['purchase_order_item_id' => $line->id, 'procurement_item_id' => $item->id, 'quantity' => 2]],
        ], $sender);
        $service = app(SampleShipmentService::class);
        $service->submit($shipment, $sender);
        $service->review($shipment, $sender);
        $service->approve($shipment, $sender);
        $service->ship($shipment, [], $sender);

        return [$shipment->fresh(), $receiver, $receiverAssignment];
    }
}
