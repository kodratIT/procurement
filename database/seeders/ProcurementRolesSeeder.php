<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\ProcurementPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProcurementRolesSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'Operasional' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::UPDATE,
            ProcurementPermissions::SUBMIT,
            ProcurementPermissions::RECEIVE,
        ],
        'Pengadaan' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::UPDATE,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            ProcurementPermissions::RECEIVE,
        ],
        'Keuangan' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::MANAGE_FINANCE,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::EXPORT,
            ProcurementPermissions::CORRECT_RECEIPT,
        ],
        'Manager' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::EXPORT,
        ],
        'Admin' => [
            ProcurementPermissions::VIEW,
            ProcurementPermissions::CREATE,
            ProcurementPermissions::UPDATE,
            ProcurementPermissions::DELETE,
            ProcurementPermissions::SUBMIT,
            ProcurementPermissions::APPROVE,
            ProcurementPermissions::EXPORT,
            ProcurementPermissions::MANAGE_MASTER_DATA,
            ProcurementPermissions::MANAGE_FINANCE,
            ProcurementPermissions::RECEIVE,
            ProcurementPermissions::CORRECT_RECEIPT,
            ProcurementPermissions::MANAGE_USERS,
            ProcurementPermissions::MANAGE_ROLES,
            ProcurementPermissions::MANAGE_FEATURES,
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
            ->mapWithKeys(fn (string $name): array => [
                $name => Permission::findOrCreate($name, 'web'),
            ]);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(
                collect($permissionNames)->map(fn (string $name) => $permissions[$name])->all(),
            );
        }

        // Also seed Shield permissions so policies with Shield can() pass in tests.
        $this->call(ShieldRoleSyncSeeder::class);
    }
}
