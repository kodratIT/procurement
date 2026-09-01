<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Services\AccessContextService;
use App\Services\InvoiceMatchingService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvoiceMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_matched_invoice_with_due_date_lines_and_private_evidence(): void
    {
        [$actor, $order, $item] = $this->invoiceContext();
        $this->assertTrue($actor->assignments()->firstOrFail()->allows(ProcurementPermissions::MANAGE_FINANCE));
        $this->assertTrue(app(MultiOfficeAuthorization::class)->allows($actor, ProcurementPermissions::MANAGE_FINANCE, $order));

        $invoice = app(InvoiceMatchingService::class)->record($order, [
            'invoice_number' => 'INV-1001',
            'total_amount' => '100.00',
            'due_date' => '2026-09-30',
            'lines' => [[
                'purchase_order_item_id' => $item->id,
                'quantity' => '10.00',
                'unit_price' => '10.00',
            ]],
            'evidence' => [[
                'file' => UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\ninvoice"),
                'metadata' => ['document_type' => 'tax_invoice'],
            ]],
        ], $actor);

        $this->assertSame('INV-1001', $invoice->invoice_number);
        $this->assertSame('2026-09-30', $invoice->due_date->toDateString());
        $this->assertSame(Invoice::MATCH_STATUS_MATCHED, $invoice->match_status);
        $this->assertSame($order->id, $invoice->purchase_order_id);
        $this->assertCount(1, $invoice->items);
        $this->assertCount(1, $invoice->attachments);
        $this->assertSame('private', $invoice->attachments->first()->disk);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
            'event' => 'invoice_recorded',
        ]);
        Storage::disk('private')->assertExists($invoice->attachments->first()->path);
    }

    public function test_invoice_amount_over_received_evidence_is_blocked_with_reason(): void
    {
        [$actor, $order] = $this->invoiceContext(receivedQuantity: '5.00');

        $exception = null;
        try {
            app(InvoiceMatchingService::class)->record($order, [
                'invoice_number' => 'INV-OVER',
                'total_amount' => '100.00',
                'due_date' => '2026-09-30',
                'evidence' => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\ninvoice")],
            ], $actor);
        } catch (ValidationException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertStringContainsString('received evidence', implode(' ', $exception->errors()['matching'] ?? []));
        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV-OVER']);
    }

    public function test_vendor_cannot_record_the_same_invoice_number_twice(): void
    {
        [$actor, $order, $item] = $this->invoiceContext();
        $service = app(InvoiceMatchingService::class);
        $data = [
            'invoice_number' => 'inv-duplicate',
            'total_amount' => '100.00',
            'due_date' => '2026-09-30',
            'lines' => [['purchase_order_item_id' => $item->id, 'quantity' => '10', 'unit_price' => '10']],
            'evidence' => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\ninvoice")],
        ];
        $service->record($order, $data, $actor);

        $this->expectException(ValidationException::class);
        $service->record($order, $data, $actor);
    }

    /** @return array{User, PurchaseOrder, PurchaseOrderItem} */
    private function invoiceContext(string $receivedQuantity = '10.00'): array
    {
        Storage::fake('private');
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $vendor = Vendor::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        $order = PurchaseOrder::factory()->create([
            'office_id' => $office->id,
            'vendor_id' => $vendor->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal_amount' => '100.00',
        ]);
        $requestItem = PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $order->purchase_request_id,
            'quantity' => '10.00',
            'unit_price' => '10.00',
            'line_total' => '100.00',
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'purchase_request_item_id' => $requestItem->id,
            'quantity' => '10.00',
            'unit_price' => '10.00',
            'line_total' => '100.00',
        ]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED, 'total_amount' => '100.00', 'subtotal_amount' => '100.00'])->save();
        $receipt = GoodsReceipt::factory()->create([
            'purchase_order_id' => $order->id,
            'receiver_id' => $actor->id,
            'office_id' => $office->id,
            'status' => GoodsReceipt::STATUS_COMPLETE,
        ]);
        GoodsReceiptItem::factory()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $item->id,
            'quantity' => $receivedQuantity,
        ]);
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $order->fresh(), $item->fresh()];
    }
}
