<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_and_assignment_tables_have_required_columns(): void
    {
        foreach (['offices', 'branches', 'departments', 'cost_centers', 'user_assignments'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table.");
            $this->assertTrue(Schema::hasColumns($table, ['is_active', 'disabled_at']));
        }

        $this->assertTrue(Schema::hasColumns('user_assignments', [
            'user_id', 'office_id', 'branch_id', 'department_id', 'cost_center_id',
            'valid_from', 'valid_until', 'is_primary',
        ]));
    }

    public function test_assignment_period_cannot_end_before_it_starts(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid_until|period|earlier/i');

        UserAssignment::create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => '2026-02-01',
            'valid_until' => '2026-01-31',
        ]);
    }
}
