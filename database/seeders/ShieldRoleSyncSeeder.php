<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\FeatureModuleService;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * ShieldRoleSyncSeeder
 *
 * Migrasi 7 role lama + super_admin ke Shield per-resource + pages permissions.
 *
 * - Sumber truth: FilamentShield::getEntitiesPermissions() (346 keys saat ini, 362 di design doc).
 *   Fallback ke Spatie Permission model jika Shield belum boot (tanpa hapus procurement.*).
 * - Mapping mengikuti docs/superpowers/specs/2026-09-03-shield-full-resource-control-design.md:
 *   Viewer: ViewAny+View semua resource + View pages
 *   Operasional: ViewAny/View/Create/Update di PurchaseRequest, Quotation, Distribution + View master + View pages
 *   Pengadaan: ViewAny/View/Create/Update/Delete master data + View pages
 *   Keuangan: ViewAny/View/Update Invoice,Budget,PurchaseOrder + View pages
 *   Manager/Manajemen/Auditor: ViewAny/View + View pages (Export/Approve tidak ada di Shield, di-cover View)
 *   Admin: semua ViewAny...Reorder semua resource + semua pages
 *   super_admin: sync semua Shield permissions
 * - Idempotent: Permission::firstOrCreate, Role::firstOrCreate + syncPermissions, DB::transaction
 * - Tidak menyentuh FeatureFlag table, hanya Cache::forget(FeatureModuleService::CACHE_KEY)
 * - Tidak menghapus procurement.* permissions (audit), hanya sync Shield Pascal keys.
 * - Tidak menyentuh Policies/Resources/Pages.
 */
final class ShieldRoleSyncSeeder extends Seeder
{
    /**
     * Semua resource subjects yang di-discovery Shield (model basename).
     * Di-derive dari FilamentShield::getEntitiesPermissions() namun di-hardcode
     * sebagai fallback agar seeder tetap idempotent tanpa boot Filament.
     *
     * @var list<string>
     */
    private const ALL_RESOURCE_SUBJECTS = [
        'Activity',
        'ApprovalInstanceStep',
        'ApproverDelegation',
        'ApproverMapping',
        'Branch',
        'Budget',
        'CostCenter',
        'Department',
        'Distribution',
        'Invoice',
        'Office',
        'Pilgrim',
        'ProcurementCategory',
        'ProcurementField',
        'ProcurementItem',
        'ProcurementUnit',
        'ProcurementVariant',
        'PurchaseOrder',
        'PurchaseRequest',
        'Quotation',
        'Role',
        'SampleShipment',
        'UmrahBatch',
        'UserAssignment',
        'Vendor',
        'Workflow',
        'WorkflowStep',
        'WorkflowVersion',
    ];

    /**
     * Master data subjects untuk Pengadaan (+ Operasional view-only).
     *
     * @var list<string>
     */
    private const MASTER_DATA_SUBJECTS = [
        'ProcurementItem',
        'ProcurementCategory',
        'ProcurementUnit',
        'ProcurementVariant',
        'ProcurementField',
        'Vendor',
        'Workflow',
        'WorkflowStep',
        'WorkflowVersion',
        'Branch',
        'Office',
        'Department',
        'CostCenter',
        'Budget',
    ];

    /**
     * Subjects yang boleh Create/Update oleh Operasional.
     *
     * @var list<string>
     */
    private const OPERATIONAL_EDITABLE_SUBJECTS = [
        'PurchaseRequest',
        'Quotation',
        'Distribution',
    ];

    /**
     * Finance subjects untuk Keuangan (Invoice, Budget, PurchaseOrder sebagai Payment).
     *
     * @var list<string>
     */
    private const FINANCE_SUBJECTS = [
        'Invoice',
        'Budget',
        'PurchaseOrder',
    ];

    /**
     * Page subjects (Shield pages dengan subject='class', prefix='view').
     * Saat ini hanya FeatureModules; Dashboard excluded di config.
     *
     * @var list<string>
     */
    private const PAGE_SUBJECTS = [
        'FeatureModules',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $shieldKeys = $this->resolveShieldPermissionKeys();

            // Upsert semua Shield permissions (tanpa hapus procurement.*).
            $permissionModels = collect($shieldKeys)
                ->mapWithKeys(function (string $name): array {
                    $permission = Permission::firstOrCreate(
                        ['name' => $name, 'guard_name' => 'web'],
                    );

                    return [$name => $permission];
                });

            // Helper: filter permission models by allowed keys (yang ada di Shield).
            $filter = function (array $allowedKeys) use ($permissionModels, $shieldKeys): array {
                $allowed = array_intersect($allowedKeys, $shieldKeys);

                return collect($allowed)
                    ->map(fn (string $k) => $permissionModels[$k])
                    ->filter()
                    ->values()
                    ->all();
            };

            // Resolve page keys dari Shield (hanya yang ada).
            $pageKeys = $this->pageKeys($shieldKeys);

            // Viewer: ViewAny+View semua resource + View pages.
            $viewerKeys = array_merge(
                $this->buildKeys(self::ALL_RESOURCE_SUBJECTS, ['ViewAny', 'View']),
                $pageKeys,
            );

            // Operasional: ViewAny/View/Create/Update di PurchaseRequest, Quotation, Distribution + View master + View pages.
            $operasionalKeys = array_merge(
                $this->buildKeys(self::OPERATIONAL_EDITABLE_SUBJECTS, ['ViewAny', 'View', 'Create', 'Update']),
                // View master = ViewAny+View untuk semua resource selain yang sudah editable (read-only master).
                // Design doc menyebut "View master" – diimplement sebagai ViewAny/View semua master + sisa resource.
                $this->buildKeys(array_diff(self::ALL_RESOURCE_SUBJECTS, self::OPERATIONAL_EDITABLE_SUBJECTS), ['ViewAny', 'View']),
                $pageKeys,
            );

            // Pengadaan: ViewAny/View/Create/Update/Delete master data + View pages.
            // "Receive" tidak ada di Shield (procurement.receive), tidak di-assign, tetap disimpan sebagai procurement.* untuk audit.
            $pengadaanKeys = array_merge(
                $this->buildKeys(self::MASTER_DATA_SUBJECTS, ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny']),
                // Pengadaan juga butuh View untuk sisa resource (read-only) agar tidak blind terhadap transaksi.
                $this->buildKeys(array_diff(self::ALL_RESOURCE_SUBJECTS, self::MASTER_DATA_SUBJECTS), ['ViewAny', 'View']),
                $pageKeys,
            );

            // Keuangan: ViewAny/View/Update Invoice,Budget,PurchaseOrder + View pages.
            // Export/CorrectReceipt adalah procurement.* lama, tidak di-assign Shield (tetap ada di DB untuk audit).
            $keuanganKeys = array_merge(
                $this->buildKeys(self::FINANCE_SUBJECTS, ['ViewAny', 'View', 'Update']),
                // Keuangan tetap bisa lihat semua resource (ViewAny/View) sesuai Manager view baseline.
                $this->buildKeys(array_diff(self::ALL_RESOURCE_SUBJECTS, self::FINANCE_SUBJECTS), ['ViewAny', 'View']),
                $pageKeys,
            );

            // Manager/Manajemen/Auditor: ViewAny/View semua resource + View pages.
            // Export/Approve adalah procurement.* lama; di Shield tidak ada affix Export/Approve untuk generic resource,
            // sehingga di-cover oleh View. Jika ada custom Approve (mis. Review:PurchaseRequest) tetap tidak diberikan ke viewer/manager
            // kecuali Admin/super_admin.
            $managerKeys = array_merge(
                $this->buildKeys(self::ALL_RESOURCE_SUBJECTS, ['ViewAny', 'View']),
                $pageKeys,
            );

            // Admin: semua Shield permissions (ViewAny...Reorder semua resource + semua pages).
            $adminKeys = $shieldKeys;

            // super_admin: sync semua Shield permissions (346/362) – sama dengan Admin namun via Gate before juga.
            $superAdminKeys = $shieldKeys;

            // Deduplicate dan filter hanya yang ada di Shield.
            $viewerKeys = array_values(array_unique($viewerKeys));
            $operasionalKeys = array_values(array_unique($operasionalKeys));
            $pengadaanKeys = array_values(array_unique($pengadaanKeys));
            $keuanganKeys = array_values(array_unique($keuanganKeys));
            $managerKeys = array_values(array_unique($managerKeys));

            $mappings = [
                'Viewer' => $viewerKeys,
                'Operasional' => $operasionalKeys,
                'Pengadaan' => $pengadaanKeys,
                'Keuangan' => $keuanganKeys,
                'Manager' => $managerKeys,
                'Manajemen' => $managerKeys,
                'Auditor' => $managerKeys,
                'Admin' => $adminKeys,
            ];

            foreach ($mappings as $roleName => $keys) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'web'],
                    [
                        'code' => Str::upper(Str::slug($roleName, '_')),
                        'is_active' => true,
                    ],
                );

                $currentProcurement = $role->permissions()->where('name', 'not like', '%:%')->pluck('name')->all();
                $desiredShield = array_values(array_unique(array_intersect($keys, $shieldKeys)));
                $allNames = array_values(array_unique(array_merge($currentProcurement, $desiredShield)));
                $models = Permission::query()->whereIn('name', $allNames)->where('guard_name', 'web')->get();
                $role->syncPermissions($models);
            }

            // super_admin – nama dari config filament-shield, default 'super_admin'.
            $superAdminName = (string) config('filament-shield.super_admin.name', 'super_admin');
            $superAdminRole = Role::query()->firstOrCreate(
                ['name' => $superAdminName, 'guard_name' => 'web'],
                [
                    'code' => Str::upper(Str::slug($superAdminName, '_')),
                    'is_active' => true,
                ],
            );
            $currentSuperProcurement = $superAdminRole->permissions()->where('name', 'not like', '%:%')->pluck('name')->all();
            $desiredSuperShield = array_values(array_unique(array_intersect($superAdminKeys, $shieldKeys)));
            $superAllNames = array_values(array_unique(array_merge($currentSuperProcurement, $desiredSuperShield)));
            $superModels = Permission::query()->whereIn('name', $superAllNames)->where('guard_name', 'web')->get();
            $superAdminRole->syncPermissions($superModels);

            // Forget Spatie permission cache agar sync langsung terlihat.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Feature flag tidak diubah, hanya forget cache seperti FeatureModuleService::mutate().
        Cache::forget(FeatureModuleService::CACHE_KEY);
    }

    /**
     * Resolve Shield permission keys.
     * Prioritas: FilamentShield::getEntitiesPermissions() -> Permission model fallback.
     *
     * @return list<string>
     */
    private function resolveShieldPermissionKeys(): array
    {
        $keys = null;

        try {
            if (class_exists(FilamentShield::class)) {
                // Pastikan Filament panel ter-set agar Shield bisa discover resources/pages.
                if (class_exists(Filament::class)) {
                    try {
                        Filament::setCurrentPanel(Filament::getPanel('admin'));
                    } catch (\Throwable $e) {
                        // Biarkan, Shield akan fallback.
                    }
                }

                $keys = FilamentShield::getEntitiesPermissions();
            }
        } catch (\Throwable $e) {
            $keys = null;
        }

        if (is_array($keys) && $keys !== []) {
            return array_values(array_unique($keys));
        }

        // Fallback: ambil dari DB yang sudah ada dengan pattern Shield (mengandung ':').
        // Ini tidak menghapus procurement.* – hanya membaca.
        try {
            $dbKeys = Permission::query()
                ->where('name', 'like', '%:%')
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            if ($dbKeys !== []) {
                return array_values(array_unique($dbKeys));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Last fallback: generate dari konstanta + config (tanpa boot Filament).
        // Build dari ALL_RESOURCE_SUBJECTS + PAGE_SUBJECTS + semua affix default.
        $defaultAffixes = config('filament-shield.policies.methods', [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ]);

        // Merge dengan resource manage custom.
        $manage = config('filament-shield.resources.manage', []);
        $allAffixes = collect($defaultAffixes)
            ->merge(collect($manage)->flatten()->all())
            ->map(fn (string $m): string => Str::studly($m))
            ->unique()
            ->values()
            ->all();

        $separator = (string) config('filament-shield.permissions.separator', ':');

        $generated = [];
        foreach (self::ALL_RESOURCE_SUBJECTS as $subject) {
            foreach ($allAffixes as $affix) {
                $generated[] = $affix.$separator.$subject;
            }
        }
        foreach (self::PAGE_SUBJECTS as $page) {
            $generated[] = 'View'.$separator.$page;
        }

        return array_values(array_unique($generated));
    }

    /**
     * Build permission keys untuk subjects + affixes.
     *
     * @param  list<string>  $subjects
     * @param  list<string>  $affixes  PascalCase affix (ViewAny, View, Create, etc)
     * @return list<string>
     */
    private function buildKeys(array $subjects, array $affixes): array
    {
        $separator = (string) config('filament-shield.permissions.separator', ':');

        $keys = [];
        foreach ($subjects as $subject) {
            foreach ($affixes as $affix) {
                $keys[] = $affix.$separator.$subject;
            }
        }

        return $keys;
    }

    /**
     * Ambil page permission keys yang ada di Shield.
     *
     * @param  list<string>  $allKeys
     * @return list<string>
     */
    private function pageKeys(array $allKeys): array
    {
        $separator = (string) config('filament-shield.permissions.separator', ':');
        $pageKeys = [];

        foreach (self::PAGE_SUBJECTS as $page) {
            $key = 'View'.$separator.$page;
            if (in_array($key, $allKeys, true)) {
                $pageKeys[] = $key;
            }
        }

        // Jika Shield mengekspos pages lain (mis. View:Settings), ambil juga yang ber-prefix View: dan bukan resource subject.
        // Ini future-proof tanpa harus hardcode.
        $resourceSubjects = self::ALL_RESOURCE_SUBJECTS;
        foreach ($allKeys as $key) {
            if (! str_starts_with($key, 'View'.$separator)) {
                continue;
            }
            $subject = explode($separator, $key, 2)[1] ?? '';
            if ($subject === '' || in_array($subject, $resourceSubjects, true) || in_array($subject, self::PAGE_SUBJECTS, true)) {
                continue;
            }
            // Anggap sebagai page permission (mis. View:ApprovalInbox jika ada).
            $pageKeys[] = $key;
        }

        return array_values(array_unique($pageKeys));
    }
}
