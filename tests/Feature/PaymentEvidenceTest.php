<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\InvoicePaymentService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

final class PaymentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_proof_is_private_stored_and_downloadable_in_scope(): void
    {
        Storage::fake('private');
        [$actor, $invoice] = $this->paymentContext();
        $payment = app(InvoicePaymentService::class)->record($invoice, [
            'amount' => '40.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-PROOF',
            'proof' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nreceipt"),
            'proof_metadata' => ['bank_reference' => 'BANK-123'],
        ], $actor);

        $proof = $payment->attachments()->firstOrFail();
        $this->assertSame('payment-proof', $proof->collection);
        $this->assertSame('private', $proof->disk);
        $this->assertSame('BANK-123', $proof->metadata['bank_reference']);
        Storage::disk('private')->assertExists($proof->path);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'event' => 'invoice_payment_recorded',
        ]);

        $response = $this->get(route('attachments.download', $proof));
        $response->assertOk();
    }

    public function test_payment_proof_can_be_added_without_changing_immutable_financial_posting(): void
    {
        Storage::fake('private');
        [$actor, $invoice] = $this->paymentContext();
        $payment = app(InvoicePaymentService::class)->record($invoice, [
            'amount' => '40.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-LATER-PROOF',
        ], $actor);

        $proof = app(InvoicePaymentService::class)->attachProof(
            $payment,
            UploadedFile::fake()->image('receipt.png'),
            ['bank_reference' => 'BANK-456'],
            $actor,
        );

        $this->assertSame($payment->id, $proof->attachable_id);
        $this->assertSame('BANK-456', $proof->metadata['bank_reference']);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'event' => 'invoice_payment_proof_attached',
        ]);

        $this->expectException(LogicException::class);
        $payment->forceFill(['amount' => '41.00'])->save();
    }

    /** @return array{User, Invoice} */
    private function paymentContext(): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $actor = User::factory()->create();
        $office = Office::factory()->create();
        $role = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $invoice = Invoice::factory()->create([
            'office_id' => $office->id,
            'total_amount' => '100.00',
            'status' => Invoice::STATUS_UNPAID,
            'review_status' => Invoice::REVIEW_STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->actingAs($actor);
        app(AccessContextService::class)->setContext($assignment);

        return [$actor, $invoice->fresh()];
    }
}
