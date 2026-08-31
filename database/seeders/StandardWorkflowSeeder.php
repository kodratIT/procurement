<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use App\Enums\WorkflowVersionStatus;
use App\Models\ApproverMapping;
use App\Models\Office;
use App\Models\Role;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Seeder;

final class StandardWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $procurementRole = Role::query()->where('name', 'Pengadaan')->firstOrFail();
        $financeRole = Role::query()->where('name', 'Keuangan')->firstOrFail();
        $workflow = Workflow::updateOrCreate(
            ['code' => 'standard-procurement'],
            [
                'name' => 'Standard Supplies Procurement',
                'description' => 'Standard supplies review followed by finance approval.',
                'is_active' => true,
            ],
        );
        $version = WorkflowVersion::updateOrCreate(
            ['workflow_id' => $workflow->getKey(), 'version_number' => 1],
            [
                'status' => WorkflowVersionStatus::Active,
                'effective_from' => now(),
                'activated_at' => now(),
                'retired_at' => null,
            ],
        );
        $review = $version->steps()->updateOrCreate(
            ['sequence' => 1],
            [
                'name' => 'Procurement Review',
                'step_type' => WorkflowStepType::Review,
                'approval_mode' => WorkflowApprovalMode::Sequential,
                'resolver_type' => 'role_in_request_office',
                'required_permission' => ProcurementPermissions::APPROVE,
                'is_required' => true,
                'settings' => ['step_key' => 'procurement_review'],
            ],
        );
        $finance = $version->steps()->updateOrCreate(
            ['sequence' => 2],
            [
                'name' => 'Finance Approval',
                'step_type' => WorkflowStepType::Approval,
                'approval_mode' => WorkflowApprovalMode::Sequential,
                'resolver_type' => 'role_in_request_office',
                'required_permission' => ProcurementPermissions::APPROVE,
                'is_required' => true,
                'settings' => [
                    'step_key' => 'finance_approval',
                    'budget_check' => ['required' => true, 'hook' => 'E8-US01'],
                ],
            ],
        );

        $offices = Office::query()->whereIn('code', ['JKT', 'SBY'])->get()->keyBy('id');
        foreach ($offices as $office) {
            ApproverMapping::updateOrCreate(
                ['workflow_step_id' => $review->getKey(), 'office_id' => $office->getKey(), 'role_id' => $procurementRole->getKey()],
                [
                    'resolver_type' => 'role_in_request_office',
                    'scope_source' => 'request_office',
                    'fallback_type' => 'block',
                    'priority' => 10,
                    'valid_from' => now()->toDateString(),
                    'is_active' => true,
                ],
            );
            ApproverMapping::updateOrCreate(
                ['workflow_step_id' => $finance->getKey(), 'office_id' => $office->getKey(), 'role_id' => $financeRole->getKey()],
                [
                    'resolver_type' => 'role_in_request_office',
                    'scope_source' => 'request_office',
                    'fallback_type' => 'block',
                    'priority' => 10,
                    'valid_from' => now()->toDateString(),
                    'is_active' => true,
                ],
            );
        }
    }
}
