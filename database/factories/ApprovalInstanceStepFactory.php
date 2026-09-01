<?php

namespace Database\Factories;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalInstanceStep> */
class ApprovalInstanceStepFactory extends Factory
{
    protected $model = ApprovalInstanceStep::class;

    public function definition(): array
    {
        return [
            'approval_instance_id' => ApprovalInstance::factory(),
            'step_order' => 1,
            'step_key' => 'procurement_review',
            'label' => 'Procurement Review',
            'resolver_type' => 'role_in_request_office',
            'approval_mode' => 'sequential',
            'step_type' => 'approval',
            'is_required' => true,
            'sla_minutes' => null,
            'escalation_type' => null,
            'approver_id' => null,
            'original_approver_id' => null,
            'approver_name' => null,
            'approver_role' => null,
            'office_id' => null,
            'branch_id' => null,
            'department_id' => null,
            'status' => 'pending',
            'decision' => null,
            'note' => null,
            'acted_by_id' => null,
            'acted_at' => null,
            'assigned_at' => null,
            'due_at' => null,
            'sla_warning_at' => null,
            'sla_warning_sent_at' => null,
            'expired_at' => null,
            'escalated_at' => null,
            'completed_at' => null,
            'context' => [],
        ];
    }
}
