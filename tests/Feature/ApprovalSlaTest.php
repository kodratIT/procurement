<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Notifications\ApprovalTaskEscalated;
use App\Notifications\ApprovalTaskSlaWarning;
use App\Services\ApprovalTaskLifecycleService;
use Database\Seeders\ProcurementRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ApprovalSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_warning_is_sent_once_before_expiration(): void
    {
        [$step, $approver] = $this->task(now()->addMinutes(30), now()->addMinutes(5));
        Notification::fake();

        $result = app(ApprovalTaskLifecycleService::class)->processSla(now()->addMinutes(6));

        $this->assertSame(1, $result['warnings']);
        $this->assertSame(0, $result['expired']);
        $this->assertNotNull($step->fresh()->sla_warning_sent_at);
        Notification::assertSentTo($approver, ApprovalTaskSlaWarning::class);

        $again = app(ApprovalTaskLifecycleService::class)->processSla(now());

        $this->assertSame(0, $again['warnings']);
    }

    public function test_expired_task_is_marked_and_escalated_with_history_preserved(): void
    {
        [$step, $approver, $fallback] = $this->task(now()->subMinutes(30), now()->subMinutes(5), withFallback: true);
        Notification::fake();

        $result = app(ApprovalTaskLifecycleService::class)->processSla(now());
        $replacement = ApprovalInstanceStep::query()
            ->where('approval_instance_id', $step->approval_instance_id)
            ->where('id', '!=', $step->id)
            ->firstOrFail();

        $this->assertSame(1, $result['expired']);
        $this->assertSame(1, $result['escalated']);
        $this->assertSame('expired', $step->fresh()->status);
        $this->assertSame('pending', $replacement->status);
        $this->assertSame($fallback->id, $replacement->approver_id);
        $this->assertDatabaseHas('approval_histories', [
            'approval_instance_step_id' => $step->id,
            'action' => 'expired',
        ]);
        $this->assertDatabaseHas('approval_histories', [
            'approval_instance_step_id' => $step->id,
            'action' => 'escalated',
        ]);
        Notification::assertSentTo($approver, ApprovalTaskSlaWarning::class);
        Notification::assertSentTo($fallback, ApprovalTaskEscalated::class);
    }

    public function test_expiration_falls_back_to_a_manager_when_no_user_is_configured(): void
    {
        [$step, $approver, $manager] = $this->task(now()->subMinutes(20), now()->subMinute(), withManager: true);
        Notification::fake();

        $result = app(ApprovalTaskLifecycleService::class)->processSla(now());
        $replacement = ApprovalInstanceStep::query()
            ->where('approval_instance_id', $step->approval_instance_id)
            ->where('id', '!=', $step->id)
            ->firstOrFail();

        $this->assertSame(1, $result['escalated']);
        $this->assertSame($manager->id, $replacement->approver_id);
        $this->assertSame($step->id, $replacement->context['escalated_from_step_id']);
    }

    public function test_pending_task_query_is_limited_to_active_instance_and_assignee(): void
    {
        [$step, $approver] = $this->task(now()->addHour(), now()->addHours(2));
        $otherUser = User::factory()->create();

        $ids = app(ApprovalTaskLifecycleService::class)->pendingTasks($approver)->pluck('id')->all();

        $this->assertSame([$step->id], $ids);
        $this->assertNotContains($step->id, app(ApprovalTaskLifecycleService::class)->pendingTasks($otherUser)->pluck('id')->all());
    }

    /** @return array{ApprovalInstanceStep, User, User|null} */
    private function task(Carbon $dueAt, Carbon $warningAt, bool $withFallback = false, bool $withManager = false): array
    {
        $this->seed(ProcurementRolesSeeder::class);
        $office = Office::factory()->create();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $this->assign($approver, $office);
        $fallback = null;
        $manager = null;

        if ($withFallback) {
            $fallback = User::factory()->create();
            $this->assign($fallback, $office);
        }

        if ($withManager) {
            $manager = User::factory()->create();
            $this->assign($manager, $office);
        }

        $request = PurchaseRequest::factory()->create([
            'requester_id' => $requester->id,
            'office_id' => $office->id,
            'status' => 'pending_approval',
        ]);
        $instance = ApprovalInstance::factory()->create([
            'purchase_request_id' => $request->id,
            'requester_id' => $requester->id,
            'submitted_by_id' => $requester->id,
            'office_id' => $office->id,
            'status' => 'in_progress',
            'workflow_version' => 2,
        ]);
        $context = [];
        if ($fallback instanceof User) {
            $context['fallback_user_id'] = $fallback->id;
        }
        $step = ApprovalInstanceStep::factory()->create([
            'approval_instance_id' => $instance->id,
            'step_order' => 1,
            'approver_id' => $approver->id,
            'original_approver_id' => $approver->id,
            'office_id' => $office->id,
            'approver_role' => 'Manager',
            'sla_minutes' => 60,
            'status' => 'pending',
            'assigned_at' => now()->subHours(2),
            'due_at' => $dueAt,
            'sla_warning_at' => $warningAt,
            'context' => $context,
        ]);

        return [$step, $approver, $fallback ?? $manager];
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
