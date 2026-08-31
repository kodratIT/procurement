<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ApprovalInbox\Pages\ListApprovalInbox;
use App\Filament\Resources\ApprovalInbox\Pages\ViewApprovalInbox;
use App\Filament\Resources\ApprovalInboxResource;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ApprovalInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_inbox_registers_scoped_list_and_detail_pages(): void
    {
        $this->assertSame(ApprovalInstanceStep::class, ApprovalInboxResource::getModel());
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
        $this->actingAs($approver);

        $ids = ApprovalInboxResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($otherTask->id, $ids);
        $this->assertNotContains($queued->id, $ids);
        $this->assertTrue(ApprovalInboxResource::canViewAny());
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
