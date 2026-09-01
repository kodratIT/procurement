<?php

namespace Database\Factories;

use App\Models\ApprovalHistory;
use App\Models\ApprovalInstanceStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalHistory> */
class ApprovalHistoryFactory extends Factory
{
    protected $model = ApprovalHistory::class;

    public function definition(): array
    {
        return [
            'approval_instance_step_id' => ApprovalInstanceStep::factory(),
            'user_id' => User::factory(),
            'action' => 'approved',
            'notes' => 'Approved.',
            'acted_at' => now(),
            'workflow_version' => 1,
            'context' => [],
        ];
    }
}
