<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_two_office_fixtures_with_representative_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Office::query()->count());
        $this->assertSame(4, Branch::query()->count());
        $this->assertSame(4, Department::query()->count());
        $this->assertSame(4, CostCenter::query()->count());
        $this->assertSame(4, UserAssignment::query()->count());
        $this->assertDatabaseHas('offices', ['code' => 'JKT', 'name' => 'Kantor Pusat Jakarta']);
        $this->assertDatabaseHas('offices', ['code' => 'SBY', 'name' => 'Kantor Regional Surabaya']);
        $this->assertDatabaseHas('users', ['email' => 'operations.jakarta@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'operations.surabaya@example.test']);
    }

    public function test_database_seeder_is_repeatable_without_duplicate_identities(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Office::query()->count());
        $this->assertSame(4, Branch::query()->count());
        $this->assertSame(4, Department::query()->count());
        $this->assertSame(4, CostCenter::query()->count());
        $this->assertSame(4, UserAssignment::query()->count());
        $this->assertSame(1, User::query()->where('email', 'operations.jakarta@example.test')->count());
        $this->assertSame(1, User::query()->where('email', 'operations.surabaya@example.test')->count());
        $this->assertSame(1, User::query()->where('email', 'procurement.cross-office@example.test')->count());
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
    }
}
