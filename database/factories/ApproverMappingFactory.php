<?php

namespace Database\Factories;

use App\Models\ApproverMapping;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApproverMapping>
 */
class ApproverMappingFactory extends Factory
{
    protected $model = ApproverMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resolver_type' => 'specific_user',
            'role_id' => null,
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'branch_id' => null,
            'department_id' => null,
            'cost_center_id' => null,
            'scope_source' => 'request_office',
            'fallback_type' => 'block',
            'fallback_role_id' => null,
            'fallback_user_id' => null,
            'priority' => 0,
            'allow_self_approval' => false,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => null,
            'is_active' => true,
            'settings' => null,
        ];
    }
}
