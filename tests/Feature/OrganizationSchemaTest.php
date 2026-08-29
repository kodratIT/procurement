<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $userId = DB::table('users')->insertGetId([
            'name' => 'Assigned User',
            'email' => 'assigned@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $officeId = DB::table('offices')->insertGetId([
            'code' => 'JKT',
            'name' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectExceptionMessageMatches('/valid_until|CHECK|constraint/i');

        DB::table('user_assignments')->insert([
            'user_id' => $userId,
            'office_id' => $officeId,
            'valid_from' => '2026-02-01',
            'valid_until' => '2026-01-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
