<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ReceiptAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_photo_metadata_is_private_and_persisted(): void
    {
        [$actor, $order, $line] = $this->context();
        Storage::fake('private');
        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);

        $attachment = app(ReceivingService::class)->attachEvidence(
            $receipt,
            UploadedFile::fake()->image('delivery.jpg'),
            'photo',
            ['captured_at' => '2026-08-31T10:00:00+07:00'],
            $actor,
        );

        $this->assertSame('private', $attachment->disk);
        $this->assertSame('photo', $attachment->metadata['evidence_type']);
        $this->assertSame('2026-08-31T10:00:00+07:00', $attachment->metadata['captured_at']);
        Storage::disk('private')->assertExists($attachment->path);
        $deliveryNote = app(ReceivingService::class)->attachEvidence(
            $receipt,
            UploadedFile::fake()->image('surat-jalan.png'),
            'surat_jalan',
            ['document_number' => 'SJ-001', 'carrier' => 'Internal courier'],
            $actor,
        );
        $this->assertSame('surat_jalan', $deliveryNote->metadata['evidence_type']);
        $this->assertSame('SJ-001', $deliveryNote->metadata['document_number']);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id, 'collection' => 'goods-receipt-photo']);
        $this->assertDatabaseHas('attachments', ['id' => $deliveryNote->id, 'collection' => 'goods-receipt-surat_jalan']);
    }

    public function test_receipt_evidence_rejects_disallowed_mime_and_oversized_files(): void
    {
        [$actor, $order, $line] = $this->context();
        Storage::fake('private');
        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);

        foreach ([
            UploadedFile::fake()->create('payload.exe', 1, 'application/x-msdownload'),
            UploadedFile::fake()->create('large.jpg', (int) ceil(config('filesystems.attachments.max_size_bytes') / 1024) + 1, 'image/jpeg'),
        ] as $file) {
            try {
                app(ReceivingService::class)->attachEvidence($receipt, $file, 'photo', [], $actor);
                $this->fail('Invalid receipt evidence must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('attachments', 0);
            }
        }

        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_photo_evidence_rejects_delivery_note_mime_types(): void
    {
        [$actor, $order, $line] = $this->context();
        Storage::fake('private');
        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);

        try {
            app(ReceivingService::class)->attachEvidence(
                $receipt,
                UploadedFile::fake()->create('delivery-note.pdf', 1, 'application/pdf'),
                'photo',
                [],
                $actor,
            );
            $this->fail('Photo evidence must reject PDF files.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_attachment_storage_rejects_a_non_private_configured_disk(): void
    {
        [$actor, $order, $line] = $this->context();
        Storage::fake('private');
        $receipt = app(ReceivingService::class)->record($order, [
            'received_date' => '2026-08-31',
            'lines' => [['purchase_order_item_id' => $line->id, 'quantity' => 1]],
        ], $actor);
        config(['filesystems.attachments.disk' => 'public']);

        try {
            app(ReceivingService::class)->attachEvidence(
                $receipt,
                UploadedFile::fake()->image('delivery.jpg'),
                'photo',
                [],
                $actor,
            );
            $this->fail('Attachment storage must remain private.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Attachments must use the private disk.', $exception->getMessage());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    /** @return array{User, PurchaseOrder, PurchaseOrderItem} */
    private function context(): array
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
        $request = PurchaseRequest::factory()->create(['office_id' => $office->id]);
        $requestItem = $request->items()->create(['item_name' => 'Uniform', 'quantity' => 1, 'unit_price' => 100, 'unit_name' => 'pcs']);
        $order = PurchaseOrder::factory()->create(['purchase_request_id' => $request->id, 'office_id' => $office->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $line = $order->items()->create(['purchase_request_item_id' => $requestItem->id, 'item_name' => 'Uniform', 'quantity' => 1, 'unit_name' => 'pcs', 'unit_price' => 100]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $order->fresh(['items']), $line->fresh()];
    }
}
