# Shield Full Resource Control — Design

**Date:** 2026-09-03
**Status:** Approved (brainstorming A)
**Topic:** Kendali resource sepenuhnya via Filament Shield dengan scope kantor tetap

## 1. Tujuan & Batasan
- **Tujuan:** `app/Filament/Resources/*` (26 resource) sepenuhnya diotorisasi Shield `ViewAny:Model`/`Create:Model` (`config/filament-shield.php:119` `separator=':' case='pascal'`) bukan `procurement.*` (`App/Support/ProcurementPermissions.php:9`).
- **Tetap:** `App/Models/UserAssignment.php:172` scope `office_id/branch_id/department_id` + tenure `valid_from/until` + `App/Services/MultiOfficeAuthorization.php:22` + `App/Services/FeatureModuleService.php:30` toggle section/fitur + `super_admin` Gate `before` (`config/filament-shield.php:71` `define_via_gate:true intercept_gate:before`).
- **Tidak diubah:** `AssignmentPermissionOverride`, `AssignmentScope`, `ActivityLog` (`is_active/disabled_at`), `spatie/laravel-permission` `teams:false` (`config/permission.php:152`).

## 2. Arsitektur
```
Filament Resource.canAccess() -> FeatureModuleService.allowsResource() -> Gate::allows('viewAny', Model) -> Policy -> Shield + Scope + Flag
```
- **Shield** = source truth `Spatie\Permission` (`config/permission.php:9` `App\Models\Role/Permission`). Key `ViewAny:PurchaseRequest` dari `policies.methods:145` + business `submit/review` via `resources.manage:188`.
- **Scope kantor layer 2:** `MultiOfficeAuthorization::allows()` + `scopeForCurrentContext:125` + `applyAssignmentScope:168` setelah `can('ViewAny:X')`.
- **Feature Flag layer 1:** `FeatureModuleService::allowsResource:123` cek `isEnabled:30` (`FeatureRegistry.php:74`) sebelum Shield. `isSuperAdmin:321` bypass.
- **Super admin:** `hasRole('super_admin')` bypass semua, tetap.

Alur `ListPurchaseRequests`: `Gate::allows('viewAny', PurchaseRequest)` -> `isEnabled(FEATURE_REQUESTS)` -> `$user->can('ViewAny:PurchaseRequest')` -> `matchesSubject` office -> scope query `where office_id = assignment.office_id`.

## 3. Katalog Permission & Mapping Role
- **Existing:** 363 permission = 349 Pascal `ViewAny:Vendor` + 14 `procurement.*`. Target hanya Pascal.
- **Katalog baru — Resources:** `resources.subject='model':187` generate `ViewAny/View/Create/Update/Delete/DeleteAny/Restore/ForceDelete/Replicate/Reorder` per model (28 model: PurchaseRequest, Quotation, PurchaseOrder, Invoice, Distribution, Vendor, Branch, Office, Department, CostCenter, Budget, Pilgrim, UmrahBatch, SampleShipment, UserAssignment, Workflow, WorkflowStep, WorkflowVersion, ProcurementItem/Category/Unit/Variant/Field, ApproverMapping/Delegation, ApprovalInstanceStep, Activity, Role). Tambahan `resources.manage`:
- **Katalog baru — Pages:** `pages.subject='class':214` generate `View:Dashboard (excluded), View:FeatureModules, View:ApprovalInbox, View:Settings` dll dari `app/Filament/Pages/*` + Filament core. Shield `shield:generate` akan buat `View:FeatureModules` etc yang dipakai `HasPageShield`.
  ```
  PurchaseRequest => ['viewAny','view','create','update','delete','submit','return','review','handoff','forward','viewTimeline']
  Workflow => ['viewAny','view','create','update','delete','activate','retire']
  Vendor => ['viewAny','view','create','update','delete','viewSensitiveData']
  ```
  Shield `shield:generate --all --panel=admin` hasilkan `Submit:PurchaseRequest` etc.
- **Mapping role lama (RolePermissionSeeder.php:16):**
  - `Viewer`: `ViewAny+View` semua resource
  - `Operasional`: `ViewAny/View/Create/Update` di PurchaseRequest/Quotation/Distribution + View master
  - `Pengadaan`: `ViewAny/View/Create/Update/Delete` master data + Receive
  - `Keuangan`: `ViewAny/View/Update` Invoice/Budget/Payment + Export/CorrectReceipt
  - `Manager/Manajemen/Auditor`: `ViewAny/View + Export/Approve`
  - `Admin`: semua `ViewAny...Reorder` semua resource
  - `super_admin`: sync semua Pascal (362) + Gate before
- Validasi via `FilamentShield::getEntitiesPermissions()` tanpa tulis DB.

## 4. Perubahan Config, Policy, Resource
**Config `config/filament-shield.php`:**
- `shield_resource.tabs:26` `resources:true pages:true widgets:true custom_permissions:false` — **fokus `resources + pages` sesuai request** (`resources:186` + `pages:212`), `widgets:232` tetap true tapi tidak dipakai di plan ini, `custom_permissions:255` dikosongkan.
- `resources.manage:188` isi mapping di atas, `exclude:197` tetap `[]`.
- `pages:212` `subject='class' prefix='view' exclude=[Dashboard::class]:216` — Shield generate `View:Settings, View:ApprovalInbox, View:FeatureModules` dll; Page `App/Filament/Pages/*` wajib `use HasPageShield` agar `canAccess` cek Shield.
- `widgets:232` tetap `exclude [AccountWidget, FilamentInfoWidget]:235` tapi tidak di-implement di fase ini.
- `policies:141` `generate:true merge:true`, `register_role_policy:true:296`.
- Publish tidak perlu (`shield:publish`), hanya config.

**Generate & Enforce:**
- `php artisan shield:generate --all --panel=admin`
- `FilamentShield::enforcePolicies()` di `AppServiceProvider::boot()` untuk `app/Policies/*` nested (karena `app/Models` -> `app/Policies` tidak auto-discovered jika nested).

**Policy contoh `app/Policies/PurchaseRequestPolicy.php:13`:**
```php
public function viewAny(User $u): bool {
  return app(FeatureModuleService::class)->featureIsAvailable(FEATURE_REQUESTS, $u)
    && $u->can('ViewAny:PurchaseRequest')
    && app(MultiOfficeAuthorization::class)->allows($u, 'ViewAny:PurchaseRequest');
}
public function submit(User $u, PurchaseRequest $r): bool {
  return $r->isCorrectable() && $r->requester_id==$u->id
    && $u->can('Submit:PurchaseRequest')
    && app(MultiOfficeAuthorization::class)->canMutate($u,$r,'Submit:PurchaseRequest',true);
}
```
Status guard tetap (`isCorrectable/status`) — pure `can('Submit:...')` tanpa guard akan bolong.

**Resource `app/Filament/Resources/PurchaseRequestResource.php:88`:**
```php
public static function canAccess(): bool {
  return app(FeatureModuleService::class)->allowsResource(self::class, fn($u)=>$u->can('ViewAny:PurchaseRequest'));
}
public static function getEloquentQuery(): Builder {
  return app(MultiOfficeAuthorization::class)->scopeForCurrentContext(parent::getEloquentQuery(), auth()->user(), 'ViewAny:PurchaseRequest');
}
```
Ganti closure `allows('procurement.view')` -> `can('ViewAny:Model')` di 26 resource.

## 5. Migrasi DB, Error Handling, Testing
**Migrasi:**
- Seeder baru `database/seeders/ShieldRoleSyncSeeder.php` (pengganti `RolePermissionSeeder.php:69`):
  ```php
  $keys = FilamentShield::getEntitiesPermissions();
  foreach($keys as $k) Permission::firstOrCreate(['name'=>$k,'guard_name'=>'web']);
  Role::findByName('Operasional')->syncPermissions([...ViewAny:PurchaseRequest...]);
  ```
  Transaksi + `Cache::forget(FeatureModuleService::CACHE_KEY)` seperti `FeatureModuleService.php:270`. `procurement.*` tidak dihapus (audit) tapi tidak di-assign baru.
- `super_admin` sync semua Pascal.

**Error handling:**
- Tanpa assignment aktif -> `allows()` null -> `whereKey(0)` (empty set) bukan 403 leak.
- Permission missing -> `UserAssignment::allows:193` catch `PermissionDoesNotExist => false`.
- `FilamentShield::prohibitDestructiveCommands(app()->isProduction())` di provider.

**Testing:**
- `tests/Feature/ShieldResourceControlTest.php`: Viewer deny `Create:PurchaseRequest`, Operasional can `ViewAny` tapi ter-scope office lain = empty, Manager approve hanya `status=SUBMITTED`, Admin full, super_admin bypass.
- Run `php artisan test --filter=ShieldResourceControl --compact` + `vendor/bin/pint --dirty --format agent`.

## 6. Non-Goals
- Tidak hapus `UserAssignment`/`MultiOfficeAuthorization` (scope tetap).
- Tidak ubah `teams` Spatie (tetap false).
- Tidak hapus `FeatureFlag` toggle.

## 7. Risiko
- Overwrite policy custom jika `shield:generate` tanpa `--ignore-existing-policies` -> merge true jaga method tambahan.
- 7 role x ~30 resource x 12 method = ~360 assignment per role -> UI RoleResource berat, butuh `simpleResourcePermissionView()` jika lag.
