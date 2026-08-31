<?php

namespace Database\Factories;

use App\Models\ApprovalInstance;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalInstance> */
class ApprovalInstanceFactory extends Factory
{
    protected $model = ApprovalInstance::class;

    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'workflow_reference' => 'standard-procurement',
            'workflow_version' => 1,
            'status' => 'pending',
            'requester_id' => User::factory(),
            'submitted_by_id' => User::factory(),
            'office_id' => null,
            'branch_id' => null,
            'department_id' => null,
            'cost_center_id' => null,
            'submitted_at' => now(),
            'context' => [],
        ];
    }
}
