<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequestStatusHistory> */
class PurchaseRequestStatusHistoryFactory extends Factory
{
    protected $model = PurchaseRequestStatusHistory::class;

    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'event' => 'submitted',
            'decision' => 'submit',
            'note' => null,
            'actor_id' => User::factory(),
            'office_id' => null,
            'branch_id' => null,
            'department_id' => null,
            'role_id' => null,
            'context' => [],
        ];
    }
}
