<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ApprovalInbox\Pages\ListApprovalInbox;
use App\Filament\Resources\ApprovalInbox\Pages\ViewApprovalInbox;
use App\Filament\Resources\ApprovalInboxResource;
use App\Models\ApprovalHistory;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\ApprovalTaskLifecycleService;
use App\Services\PurchaseRequestCancellationService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Leek\FilamentHeaderFilters\Concerns\HasHeaderFilters;
use Tests\TestCase;

final class ApprovalInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_inbox_registers_unified_purchase_request_list_and_detail_pages(): void
    {
        $this->assertSame(PurchaseRequest::class, ApprovalInboxResource::getModel());
        $this->assertSame(ListApprovalInbox::class, ApprovalInboxResource::getPages()['index']->getPage());
        $this->assertSame(ViewApprovalInbox::class, ApprovalInboxResource::getPages()['view']->getPage());
    }

    public function test_approval_inbox_only_lists_pending_tasks_for_the_authenticated_approver(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $approver = User::factory()->create();
        $other = User::factory()->create();
        $this->assign($approver, $office);
        $this->assign($other, $office);
        $request = PurchaseRequest::factory()->create(['office_id' => $office->id, 'status' => 'pending_approval']);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $request->requester_id,
            'office_id' => $office->id,
            'status' => 'in_progress',
        ]);
        $mine = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'approver_id' => $approver->id,
            'office_id' => $office->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);
        $otherTask = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_order' => 2,
            'approver_id' => $other->id,
            'office_id' => $office->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);
        $queued = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_order' => 3,
            'approver_id' => $approver->id,
            'office_id' => $office->id,
            'status' => 'queued',
        ]);
        $laterMine = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_order' => 2,
            'approver_id' => $approver->id,
            'office_id' => $office->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);
        $otherRequest = PurchaseRequest::factory()->create(['office_id' => $office->id, 'status' => 'pending_approval']);
        $otherInstance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $otherRequest->id,
            'requester_id' => $otherRequest->requester_id,
            'submitted_by_id' => $otherRequest->requester_id,
            'office_id' => $office->id,
            'status' => 'in_progress',
        ]);
        ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $otherInstance->id,
            'approver_id' => $other->id,
            'office_id' => $office->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        $this->actingAs($approver);

        $currentTaskIds = app(ApprovalTaskLifecycleService::class)->pendingTasks($approver)->pluck('id')->all();
        $this->assertSame([$mine->id], $currentTaskIds);

        $mine->forceFill(['status' => 'approved'])->save();
        $currentTaskIds = app(ApprovalTaskLifecycleService::class)->pendingTasks($approver)->pluck('id')->all();
        $this->assertSame([$laterMine->id], $currentTaskIds);

        $ids = ApprovalInboxResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$request->id], $ids);
        $this->assertTrue(ApprovalInboxResource::canViewAny());
    }

    public function test_active_and_archive_tabs_separate_pending_tasks_from_requests_approved_by_the_user(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $approver = User::factory()->create();
        $otherApprover = User::factory()->create();
        $this->assign($approver, $office);
        $this->assign($otherApprover, $office);

        [$activeRequest] = $this->approvalFor($office, $approver, 'pending_approval', 'in_progress', 'pending');
        [$archivedRequest, , $archivedStep] = $this->approvalFor($office, $approver, 'approved', 'approved', 'approved');
        ApprovalHistory::factory()->for($archivedStep, 'approvalInstanceStep')->create([
            'user_id' => $approver->getKey(),
            'action' => 'approve',
        ]);

        [$returnedRequest, , $returnedStep] = $this->approvalFor($office, $approver, 'returned', 'returned', 'approved');
        ApprovalHistory::factory()->for($returnedStep, 'approvalInstanceStep')->create([
            'user_id' => $approver->getKey(),
            'action' => 'approve',
        ]);

        [$otherRequest, , $otherStep] = $this->approvalFor($office, $otherApprover, 'approved', 'approved', 'approved');
        ApprovalHistory::factory()->for($otherStep, 'approvalInstanceStep')->create([
            'user_id' => $otherApprover->getKey(),
            'action' => 'approve',
        ]);

        $this->actingAs($approver);

        $this->assertSame([$activeRequest->getKey()], ApprovalInboxResource::activeQuery($approver)->pluck('id')->all());
        $this->assertSame('1', ApprovalInboxResource::getNavigationBadge());
        $this->assertSame([$archivedRequest->getKey()], ApprovalInboxResource::archiveQuery($approver)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$activeRequest->getKey(), $archivedRequest->getKey()],
            ApprovalInboxResource::getEloquentQuery()->pluck('id')->all(),
        );
        $this->assertNotContains($returnedRequest->getKey(), ApprovalInboxResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($otherRequest->getKey(), ApprovalInboxResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(ApprovalInboxResource::canView($archivedRequest));

        $tabs = (new ListApprovalInbox)->getTabs();
        $this->assertSame(['active', 'archive'], array_keys($tabs));
        $this->assertSame('Aktif', $tabs['active']->getLabel());
        $this->assertSame('Arsip', $tabs['archive']->getLabel());
        $this->assertContains(HasHeaderFilters::class, class_uses_recursive(ListApprovalInbox::class));
    }

    public function test_purchase_request_can_only_be_cancelled_before_entering_an_approval_stage(): void
    {
        $office = Office::factory()->create();
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->getKey(),
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);
        $service = app(PurchaseRequestCancellationService::class);

        $this->assertTrue($service->canCancel($request));

        ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->getKey(),
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $request->requester_id,
            'office_id' => $office->getKey(),
            'status' => 'in_progress',
        ]);

        $this->assertFalse($service->canCancel($request));

        $request->forceFill(['status' => PurchaseRequest::STATUS_PENDING_APPROVAL])->save();
        $this->assertFalse($service->canCancel($request));
    }

    /** @return array{PurchaseRequest, ApprovalInstance, ApprovalInstanceStep} */
    private function approvalFor(
        Office $office,
        User $approver,
        string $requestStatus,
        string $instanceStatus,
        string $stepStatus,
    ): array {
        $request = PurchaseRequest::factory()->create([
            'office_id' => $office->getKey(),
            'status' => $requestStatus,
        ]);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->getKey(),
            'requester_id' => $request->requester_id,
            'submitted_by_id' => $request->requester_id,
            'office_id' => $office->getKey(),
            'status' => $instanceStatus,
        ]);
        $step = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->getKey(),
            'approver_id' => $approver->getKey(),
            'office_id' => $office->getKey(),
            'status' => $stepStatus,
            'assigned_at' => now(),
        ]);

        return [$request, $instance, $step];
    }

    private function assign(User $user, Office $office): UserAssignment
    {
        $role = Role::query()->where('name', 'Manager')->firstOrFail();

        return UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => Carbon::today()->subDay(),
            'is_primary' => true,
        ]);
    }
}
