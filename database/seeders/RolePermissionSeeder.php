<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'Admin' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::UPDATE,
            ProcurementPermissions::DELETE,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::EXPORT,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            ProcurementPermissions::MANAGE_FINANCE,
            ProcurementPermissions::RECEIVE,
            ProcurementPermissions::CORRECT_RECEIPT,
            ProcurementPermissions::MANAGE_USERS,
            ProcurementPermissions::MANAGE_ROLES,
        ],
        'Operasional' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::RECEIVE,
        ],
        'Pengadaan' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::UPDATE,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            ProcurementPermissions::RECEIVE,
        ],
        'Keuangan' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::MANAGE_FINANCE,
            ProcurementPermissions::EXPORT,
            ProcurementPermissions::CORRECT_RECEIPT,
        ],
        'Manager' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::EXPORT,
        ],
        'Manajemen' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::EXPORT,
        ],
        'Auditor' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::EXPORT,
        ],
        'Viewer' => [ProcurementPermissions::VIEW],
    ];

    public function run(): void
    {
        $permissions = collect(ProcurementPermissions::all())
            ->mapWithKeys(function (string $name): array {
                [$module, $action] = array_pad(explode('.', $name, 2), 2, null);

                $permission = Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'code' => Str::upper(Str::slug($name, '_')),
                        'module' => $module,
                        'action' => $action,
                    ],
                );

                return [$name => $permission];
            });

        foreach (self::ROLE_PERMISSIONS as $name => $permissionNames) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'code' => Str::upper(Str::slug($name, '_')),
                    'is_active' => true,
                ],
            );

            $role->syncPermissions(
                collect($permissionNames)->map(fn (string $permissionName): Permission => $permissions[$permissionName])->all(),
            );
        }
    }
}
