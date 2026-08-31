<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\InvoicePaymentService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PaymentRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_payment_status_projects_from_unpaid_to_partial_to_paid(): void
    {
        [$actor, $invoice] = $this->paymentContext();
        $service = app(InvoicePaymentService::class);

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->paymentStatus());
        $this->assertSame('100.00', $invoice->outstandingAmount());

        $first = $service->record($invoice, [
            'amount' => '40.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-1',
        ], $actor);

        $this->assertSame($actor->id, $first->recorded_by_id);
        $this->assertSame('2026-09-15', $first->payment_date->toDateString());
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->paymentStatus());
        $this->assertSame('60.00', $invoice->fresh()->outstandingAmount());

        $service->record($invoice, [
            'amount' => '60.00',
            'payment_date' => '2026-09-20',
            'reference_number' => 'PAY-2',
        ], $actor);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->paymentStatus());
        $this->assertSame('0.00', $invoice->fresh()->outstandingAmount());
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_overpayment_is_rejected_atomically(): void
    {
        [$actor, $invoice] = $this->paymentContext();
        $service = app(InvoicePaymentService::class);
        $service->record($invoice, [
            'amount' => '75.00',
            'payment_date' => '2026-09-15',
            'reference_number' => 'PAY-75',
        ], $actor);

        $this->expectException(ValidationException::class);
        try {
            $service->record($invoice, [
                'amount' => '25.01',
                'payment_date' => '2026-09-16',
                'reference_number' => 'PAY-OVER',
            ], $actor);
        } finally {
            $this->assertDatabaseCount('payments', 1);
            $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->paymentStatus());
        }
    }

    public function test_payment_mutation_outside_invoice_scope_is_denied_and_audited(): void
    {
        $otherOffice = Office::factory()->create();
        [$actor, $invoice] = $this->paymentContext();
        $otherAssignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $otherOffice->id,
            'role_id' => Role::query()->where('name', 'Keuangan')->firstOrFail()->id,
            'role' => 'Keuangan',
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        app(AccessContextService::class)->setContext($otherAssignment);

        try {
            app(InvoicePaymentService::class)->record($invoice, [
                'amount' => '10.00',
                'payment_date' => '2026-09-15',
                'reference_number' => 'PAY-DENIED',
            ], $actor);
            $this->fail('An out-of-scope payment mutation must fail.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payments', 0);
        }

        $activity = Activity::query()->where('event', 'invoice_payment_mutation_denied')->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $activity->causer_id);
        $this->assertSame('record', $activity->properties['operation']);
        $this->assertSame($invoice->id, $activity->subject_id);
    }

    public function test_payment_mutation_without_finance_permission_is_denied_and_audited(): void
    {
        [$actor, $invoice] = $this->paymentContext();
        $viewerRole = Role::query()->where('name', 'Viewer')->firstOrFail();
        $viewerAssignment = UserAssignment::factory()->create([
            'user_id' => $actor->id,
            'office_id' => $invoice->office_id,
            'role_id' => $viewerRole->id,
            'role' => $viewerRole->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        app(AccessContextService::class)->setContext($viewerAssignment);

        try {
            app(InvoicePaymentService::class)->record($invoice, [
                'amount' => '10.00',
                'payment_date' => '2026-09-15',
                'reference_number' => 'PAY-NO-FINANCE',
            ], $actor);
            $this->fail('A non-finance payment mutation must fail.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payments', 0);
        }

        $this->assertDatabaseHas('activity_log', [
            'event' => 'invoice_payment_mutation_denied',
            'subject_id' => $invoice->id,
            'causer_id' => $actor->id,
        ]);
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
