<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationSeeder::class,
            ProcurementMasterSeeder::class,
            StandardWorkflowSeeder::class,
            UmrahBatchSeeder::class,
            PilgrimSeeder::class,
        ]);
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'is_active' => true],
        );
    }
}
