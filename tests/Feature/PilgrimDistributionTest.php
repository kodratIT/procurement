<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\GoodsReceipt;
use App\Models\Office;
use App\Models\Pilgrim;
use App\Models\ProcurementItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\DistributionService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PilgrimDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_receipt_is_created_and_evidence_is_private(): void
    {
        [$actor, $batch, $item] = $this->context(5);
        Storage::fake('private');
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();

        $allocation = app(DistributionService::class)->confirmPilgrimReceipt($item, $pilgrim, [
            'quantity' => '2.00',
            'evidence' => [UploadedFile::fake()->image('receipt.jpg')],
        ], $actor);

        $this->assertSame('2.00', (string) $allocation->quantity);
        $this->assertSame('received', $allocation->status);
        $this->assertCount(1, $allocation->attachments);
        $this->assertSame('private', $allocation->attachments->first()->disk);
        Storage::disk('private')->assertExists($allocation->attachments->first()->path);
    }

    public function test_cumulative_pilgrim_allocation_cannot_exceed_distribution_and_rolls_back(): void
    {
        [$actor, $batch, $item] = $this->context(5);
        $first = Pilgrim::factory()->forBatch($batch)->create();
        $second = Pilgrim::factory()->forBatch($batch)->create();
        $service = app(DistributionService::class);
        $service->confirmPilgrimReceipt($item, $first, ['quantity' => 3], $actor);

        try {
            $service->confirmPilgrimReceipt($item, $second, ['quantity' => 3], $actor);
            $this->fail('An over-allocation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertDatabaseCount('pilgrim_distribution_items', 1);
        $this->assertDatabaseHas('pilgrim_distribution_items', [
            'pilgrim_id' => $first->id,
            'quantity' => '3.00',
            'status' => 'received',
        ]);
    }

    public function test_receipt_requires_the_same_active_batch_and_organizational_scope(): void
    {
        [$actor, $batch, $item] = $this->context(5);
        $otherBatch = UmrahBatch::factory()->forOffice($batch->office)->open()->create();
        $wrongBatchPilgrim = Pilgrim::factory()->forBatch($otherBatch)->create();
        $service = app(DistributionService::class);

        try {
            $service->confirmPilgrimReceipt($item, $wrongBatchPilgrim, ['quantity' => 1], $actor);
            $this->fail('A pilgrim from another batch must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pilgrim_id', $exception->errors());
        }

        $wrongScopePilgrim = Pilgrim::withoutEvents(function () use ($batch): Pilgrim {
            return Pilgrim::factory()->create([
                'umrah_batch_id' => $batch->id,
                'office_id' => Office::factory()->create()->id,
            ]);
        });
        try {
            $service->confirmPilgrimReceipt($item, $wrongScopePilgrim, ['quantity' => 1], $actor);
            $this->fail('A pilgrim outside the batch office must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pilgrim_id', $exception->errors());
        }

        $this->assertDatabaseCount('pilgrim_distribution_items', 0);
    }

    public function test_pending_and_rejected_statuses_do_not_count_as_received(): void
    {
        [$actor, $batch, $item] = $this->context(5);
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();
        $service = app(DistributionService::class);
        $pending = $service->recordPilgrimReceipt($item, $pilgrim, ['quantity' => 2], $actor);
        $this->assertSame('pending', $pending->status);

        $rejected = $service->rejectPilgrimReceipt($item, $pilgrim, [], $actor);
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame(0, (int) $item->pilgrimAllocations()->where('status', 'received')->sum('quantity'));
    }

    public function test_invalid_evidence_rolls_back_the_pilgrim_receipt(): void
    {
        [$actor, $batch, $item] = $this->context(5);
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();
        $service = app(DistributionService::class);

        try {
            $service->confirmPilgrimReceipt($item, $pilgrim, [
                'quantity' => 1,
                'evidence' => [UploadedFile::fake()->create('bad.exe', 1, 'application/x-msdownload')],
            ], $actor);
            $this->fail('Invalid evidence must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseCount('pilgrim_distribution_items', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_batch_mode_excludes_pilgrim_receipts(): void
    {
        [$actor, $batch, $item] = $this->context(5, Distribution::RECEIPT_MODE_BATCH);
        $pilgrim = Pilgrim::factory()->forBatch($batch)->create();

        $this->expectException(ValidationException::class);
        app(DistributionService::class)->confirmPilgrimReceipt($item, $pilgrim, ['quantity' => 1], $actor);
    }

    /** @return array{User, UmrahBatch, DistributionItem} */
    private function context(int $quantity, string $mode = Distribution::RECEIPT_MODE_INDIVIDUAL): array
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

        $procurementItem = ProcurementItem::factory()->create();
        $request = PurchaseRequest::factory()->create(['office_id' => $office->id]);
        $requestItem = $request->items()->create([
            'item_name' => 'Uniform',
            'quantity' => $quantity,
            'unit_price' => 100,
            'unit_name' => 'pcs',
            'procurement_item_id' => $procurementItem->id,
        ]);
        $order = PurchaseOrder::factory()->create([
            'purchase_request_id' => $request->id,
            'office_id' => $office->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $orderItem = $order->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'procurement_item_id' => $procurementItem->id,
            'item_name' => 'Uniform',
            'quantity' => $quantity,
            'unit_name' => 'pcs',
            'unit_price' => 100,
        ]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->saveQuietly();
        $receipt = GoodsReceipt::factory()->create([
            'purchase_order_id' => $order->id,
            'receiver_id' => $actor->id,
            'office_id' => $office->id,
            'status' => GoodsReceipt::STATUS_COMPLETE,
        ]);
        $receipt->items()->create(['purchase_order_item_id' => $orderItem->id, 'quantity' => $quantity]);

        $batch = UmrahBatch::factory()->forOffice($office)->open()->create();
        $distribution = app(DistributionService::class)->record($batch, [
            'distributed_at' => now()->toDateString(),
            'receipt_mode' => $mode,
            'lines' => [['procurement_item_id' => $procurementItem->id, 'quantity' => $quantity]],
        ], $actor);

        return [$actor, $batch, $distribution->items->firstOrFail()];
    }
}
