<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApproverMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_and_delegation_schema_records_scope_and_validity_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('approver_mappings', [
            'workflow_step_id',
            'resolver_type',
            'role_id',
            'user_id',
            'office_id',
            'branch_id',
            'department_id',
            'cost_center_id',
            'scope_source',
            'fallback_type',
            'valid_from',
            'valid_until',
            'allow_self_approval',
        ]));
        $this->assertTrue(Schema::hasColumns('approver_delegations', [
            'delegator_id',
            'delegate_id',
            'valid_from',
            'valid_until',
            'reason',
            'is_active',
        ]));
    }

    public function test_mapping_and_delegation_only_match_active_valid_dates(): void
    {
        $mapping = ApproverMapping::factory()->create([
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);
        $delegator = User::factory()->create();
        $delegate = User::factory()->create();
        $delegation = ApproverDelegation::factory()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $this->assertTrue($mapping->fresh()->isActiveAt());
        $this->assertCount(1, ApproverMapping::query()->activeAt()->whereKey($mapping)->get());
        $this->assertTrue($delegation->fresh()->isActiveAt());
        $this->assertCount(1, ApproverDelegation::query()->activeAt()->whereKey($delegation)->get());
        $this->assertCount(0, ApproverDelegation::query()->activeAt(Carbon::today()->addDays(2))->whereKey($delegation)->get());
    }

    public function test_mapping_requires_a_role_or_specific_user_and_valid_fallback(): void
    {
        $this->expectException(ValidationException::class);

        ApproverMapping::factory()->create([
            'role_id' => null,
            'user_id' => null,
        ]);
    }

    public function test_delegation_requires_a_reason_and_distinct_users(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        ApproverDelegation::factory()->create([
            'delegator_id' => $user->id,
            'delegate_id' => $user->id,
            'reason' => null,
        ]);
    }
}
