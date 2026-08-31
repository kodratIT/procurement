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
use App\Services\InvoicePaymentService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvoiceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_requires_a_successful_match_and_payments_derive_status(): void
    {
        [$actor, $order, $item] = $this->invoiceContext();
        $invoice = app(InvoiceMatchingService::class)->record($order, [
            'invoice_number' => 'INV-REVIEW',
            'total_amount' => '100.00',
            'due_date' => '2026-09-30',
            'lines' => [['purchase_order_item_id' => $item->id, 'quantity' => '10', 'unit_price' => '10']],
            'evidence' => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\ninvoice")],
        ], $actor);
        $invoice = app(InvoiceMatchingService::class)->approve($invoice, $actor);

        $this->assertSame(Invoice::REVIEW_STATUS_APPROVED, $invoice->review_status);
        $paymentService = app(InvoicePaymentService::class);
        $paymentService->record($invoice, [
            'amount' => '40.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-1',
        ], $actor);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'amount' => '40.00']);
        $this->assertSame('40.00', $invoice->fresh()->paymentTotal());
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->status);

        $paymentService->record($invoice, [
            'amount' => '60.00',
            'payment_date' => '2026-09-20',
            'reference_number' => 'PAY-2',
        ], $actor);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_payment_cannot_be_recorded_before_invoice_review_approval(): void
    {
        [$actor, $order, $item] = $this->invoiceContext();
        $invoice = app(InvoiceMatchingService::class)->record($order, [
            'invoice_number' => 'INV-PENDING',
            'total_amount' => '100.00',
            'due_date' => '2026-09-30',
            'lines' => [['purchase_order_item_id' => $item->id, 'quantity' => '10', 'unit_price' => '10']],
            'evidence' => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\ninvoice")],
        ], $actor);

        $this->expectException(ValidationException::class);
        app(InvoicePaymentService::class)->record($invoice, [
            'amount' => '10.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-PENDING',
        ], $actor);
    }

    /** @return array{User, PurchaseOrder, PurchaseOrderItem} */
    private function invoiceContext(): array
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
            'quantity' => '10.00',
        ]);
        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $order->fresh(), $item->fresh()];
    }
}
