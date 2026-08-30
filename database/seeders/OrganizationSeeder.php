<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            'JKT' => Office::updateOrCreate(
                ['code' => 'JKT'],
                ['name' => 'Kantor Pusat Jakarta', 'is_active' => true, 'disabled_at' => null],
            ),
            'SBY' => Office::updateOrCreate(
                ['code' => 'SBY'],
                ['name' => 'Kantor Regional Surabaya', 'is_active' => true, 'disabled_at' => null],
            ),
        ];

        $branches = [];
        foreach ([
            ['JKT', 'JKT-OPS', 'Cabang Operasional Jakarta'],
            ['JKT', 'JKT-MKT', 'Cabang Marketing Jakarta'],
            ['SBY', 'SBY-OPS', 'Cabang Operasional Surabaya'],
            ['SBY', 'SBY-MKT', 'Cabang Marketing Surabaya'],
        ] as [$officeCode, $code, $name]) {
            $branches[$code] = Branch::updateOrCreate(
                ['office_id' => $offices[$officeCode]->id, 'code' => $code],
                ['name' => $name, 'is_active' => true, 'disabled_at' => null],
            );
        }

        $departments = [];
        foreach ([
            ['JKT', 'JKT-OPS', 'OPS-JKT', 'Operasional Jakarta'],
            ['JKT', null, 'PROC-JKT', 'Pengadaan Jakarta'],
            ['SBY', 'SBY-OPS', 'OPS-SBY', 'Operasional Surabaya'],
            ['SBY', null, 'FIN-SBY', 'Keuangan Surabaya'],
        ] as [$officeCode, $branchCode, $code, $name]) {
            $departments[$code] = Department::updateOrCreate(
                ['office_id' => $offices[$officeCode]->id, 'code' => $code],
                [
                    'branch_id' => $branchCode === null ? null : $branches[$branchCode]->id,
                    'name' => $name,
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );
        }

        $costCenters = [];
        foreach ([
            ['JKT', 'CC-JKT-OPS', 'Operasional Kantor Jakarta'],
            ['JKT', 'CC-JKT-PRC', 'Pengadaan Jakarta'],
            ['SBY', 'CC-SBY-OPS', 'Operasional Kantor Surabaya'],
            ['SBY', 'CC-SBY-PRC', 'Pengadaan Surabaya'],
        ] as [$officeCode, $code, $name]) {
            $costCenters[$code] = CostCenter::updateOrCreate(
                ['office_id' => $offices[$officeCode]->id, 'code' => $code],
                ['name' => $name, 'is_active' => true, 'disabled_at' => null],
            );
        }

        $users = [
            'jakarta' => User::updateOrCreate(
                ['email' => 'operations.jakarta@example.test'],
                ['name' => 'Operations Jakarta', 'keycloak_sub' => 'seed-operations-jakarta', 'is_active' => true],
            ),
            'surabaya' => User::updateOrCreate(
                ['email' => 'operations.surabaya@example.test'],
                ['name' => 'Operations Surabaya', 'keycloak_sub' => 'seed-operations-surabaya', 'is_active' => true],
            ),
            'crossOffice' => User::updateOrCreate(
                ['email' => 'procurement.cross-office@example.test'],
                ['name' => 'Procurement Cross Office', 'keycloak_sub' => 'seed-procurement-cross-office', 'is_active' => true],
            ),
        ];

        foreach ([
            ['jakarta', 'JKT', 'JKT-OPS', 'OPS-JKT', 'CC-JKT-OPS', 'Operasional', true],
            ['surabaya', 'SBY', 'SBY-OPS', 'OPS-SBY', 'CC-SBY-OPS', 'Operasional', true],
            ['crossOffice', 'JKT', null, 'PROC-JKT', 'CC-JKT-PRC', 'Pengadaan', true],
            ['crossOffice', 'SBY', null, 'FIN-SBY', 'CC-SBY-PRC', 'Pengadaan', false],
        ] as [$userKey, $officeCode, $branchCode, $departmentCode, $costCenterCode, $role, $isPrimary]) {
            $identity = [
                'user_id' => $users[$userKey]->id,
                'office_id' => $offices[$officeCode]->id,
            ];
            $assignment = UserAssignment::query()
                ->where($identity)
                ->whereDate('valid_from', '2026-01-01')
                ->first() ?? new UserAssignment($identity + ['valid_from' => '2026-01-01']);

            $assignment->fill([
                'branch_id' => $branchCode === null ? null : $branches[$branchCode]->id,
                'department_id' => $departments[$departmentCode]->id,
                'cost_center_id' => $costCenters[$costCenterCode]->id,
                'role' => $role,
                'valid_from' => '2026-01-01',
                'valid_until' => null,
                'is_primary' => $isPrimary,
                'is_active' => true,
                'disabled_at' => null,
            ])->save();
        }
    }
}
