# Umrah Procurement — Setup & Usage A–Z

Panduan ini menjelaskan instalasi, konfigurasi, login, penggunaan modul, operasi harian, dan troubleshooting aplikasi Umrah Procurement.

Aplikasi ini adalah Laravel 13 + Filament 5 untuk procurement operator umrah dengan:

- Keycloak SSO melalui OIDC Authorization Code + PKCE.
- Role dan permission berbasis Spatie Permission + Filament Shield.
- Pembatasan akses berdasarkan kantor aktif, assignment, dan periode berlaku.
- Workflow approval procurement.
- Audit log dan ekspor CSV.
- PostgreSQL/Redis untuk deployment production-like; SQLite/database driver untuk local development.

> **Catatan penting:** login aplikasi bukan login email/password lokal. User masuk melalui Keycloak, kemudian aplikasi melakukan provisioning atau pencocokan user lokal berdasarkan Keycloak `sub`.
Untuk panduan penggunaan setiap fitur secara berurutan, lihat [PROCUREMENT-FEATURE-USER-GUIDE.md](./PROCUREMENT-FEATURE-USER-GUIDE.md).

---
php p
## 1. Prasyarat

### Runtime

| Komponen | Minimum/proyek |
| --- | --- |
| PHP | `8.3+`; baseline CI/container `8.4` |
| Composer | Composer 2 |
| Node.js | `20.19+` |
| npm | `10.8+` (lockfile dibuat dengan npm 10.8.2+) |
| Database production-like | PostgreSQL 16 |
| Cache/queue production-like | Redis 7 |
| Database lokal alternatif | SQLite dengan ekstensi `pdo_sqlite` |

Versi dependency yang terpasang dapat dicek dengan:

```sh
php -v
composer --version
node --version
npm --version
composer show --direct
```

Gunakan `composer.lock` dan `package-lock.json` yang ada. Jangan mengganti instalasi deterministik `npm ci` dengan `npm install` tanpa alasan.

### Tool tambahan

- Git.
- Docker Desktop, jika memakai PostgreSQL/Redis dari `compose.yaml`.
- Realm Keycloak dan client OIDC yang dapat diakses oleh browser serta server Laravel.

---

## 2. Mendapatkan source code

```sh
git clone <repository-url>
cd procurement
```

Jika source sudah tersedia:

```sh
cd /path/ke/procurement
```

Semua perintah Artisan di panduan ini dijalankan dari root repository, yaitu folder yang berisi `artisan` dan `composer.json`.

---

## 3. Pilih mode database

Pilih satu mode sebelum mengisi `.env`:

| Mode | Cocok untuk | Database | Cache/queue |
| --- | --- | --- | --- |
| Local SQLite | Pengembangan cepat | File `database/database.sqlite` | Database |
| Local MySQL | Menggunakan MySQL lokal/container yang sudah ada | MySQL 8.x | Database |
| Docker Compose | Stack yang mendekati deployment | PostgreSQL 16 | Redis 7 |

Jangan mencampur host database antar-mode. Khusus Docker Compose, container `app` memakai hostname `postgres` dan `redis`; `127.0.0.1` di dalam container bukan komputer host.

---

## 4. Instalasi lokal dengan SQLite

Ini jalur setup paling pendek untuk development.

### 4.1 Buat environment file

```sh
cp .env.example .env
touch database/database.sqlite
```

`.env.example` memang menggunakan SQLite sebagai default lokal. File SQLite diabaikan Git melalui `database/.gitignore`.

### 4.2 Install dependency

```sh
composer install --no-interaction --prefer-dist
npm ci
```

### 4.3 Generate application key

```sh
php artisan key:generate --no-interaction
```

### 4.4 Migrasi dan seed data

```sh
php artisan migrate --seed --no-interaction
```

Seed utama dijalankan oleh `DatabaseSeeder` dengan urutan:

1. Role dan permission.
2. Office, branch, department, cost center, user, dan assignment contoh.
3. Master procurement.
4. Standard workflow.
5. Umrah batch.
6. Pilgrim.

### 4.5 Build asset frontend

```sh
npm run build
```

### 4.6 Validasi aplikasi

```sh
php artisan about
php artisan migrate:status --no-interaction
php artisan route:list
```

Validasi Keycloak dijalankan setelah nilai `KEYCLOAK_*` diisi:

```sh
php artisan app:validate-environment --no-interaction
```

### 4.7 Jalankan server

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Buka:

- Aplikasi: <http://127.0.0.1:8000>
- Admin panel: <http://127.0.0.1:8000/admin>
- Liveness/readiness: <http://127.0.0.1:8000/up>
- Readiness alias: <http://127.0.0.1:8000/health/ready>

Untuk hot reload selama development, gunakan dua proses atau script Composer:

```sh
composer dev
```

Script tersebut menjalankan `php artisan dev`, yang menyiapkan server Laravel dan Vite sesuai konfigurasi proyek.

---

## 5. Instalasi dengan MySQL lokal/container

Gunakan bagian ini hanya jika environment memang memakai MySQL. Jalur Docker Compose resmi proyek menggunakan PostgreSQL, bukan MySQL.

### 5.1 Siapkan database dan user aplikasi

Contoh SQL berikut harus dijalankan oleh administrator MySQL. Ganti placeholder password tanpa menuliskannya ke dokumentasi atau commit:

```sql
CREATE DATABASE IF NOT EXISTS umrah_procurement
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'umrah_procurement'@'%'
  IDENTIFIED BY '<DB_PASSWORD>';

ALTER USER 'umrah_procurement'@'%'
  IDENTIFIED BY '<DB_PASSWORD>';

GRANT ALL PRIVILEGES ON umrah_procurement.*
  TO 'umrah_procurement'@'%';

FLUSH PRIVILEGES;
```

Gunakan user aplikasi khusus, bukan root.

### 5.2 Isi `.env`

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=umrah_procurement
DB_USERNAME=umrah_procurement
DB_PASSWORD=<DB_PASSWORD>

QUEUE_CONNECTION=database
CACHE_STORE=database
```

Jika port MySQL dipublish dari Docker tetapi IPv4 host bentrok dengan MySQL lain, host-run Artisan pada macOS dapat memerlukan:

```dotenv
DB_HOST=::1
```

Uji koneksi sebelum migrasi:

```sh
php artisan db:show
```

### 5.3 Install dan migrate

```sh
composer install --no-interaction --prefer-dist
npm ci
php artisan key:generate --no-interaction
php artisan migrate --seed --no-interaction
npm run build
php artisan about
```

Jika migrasi gagal karena nama index/constraint terlalu panjang, periksa migrasi yang gagal dan gunakan nama index eksplisit yang pendek. Jangan menghapus database lain pada container yang sama. Reset hanya database `umrah_procurement` jika memang ingin mengulang dari kosong.

---

## 6. Instalasi dengan Docker Compose

`compose.yaml` menyediakan:

- `app` — Laravel pada port `8000`.
- `postgres` — PostgreSQL 16.
- `redis` — Redis 7.

### 6.1 Isi credential Compose

```sh
cp .env.example .env
```

Pastikan nilai berikut ada di `.env`, karena dipakai untuk membuat container PostgreSQL:

```dotenv
DB_DATABASE=umrah_procurement
DB_USERNAME=laravel
DB_PASSWORD=<STRONG_DB_PASSWORD>
```

`compose.yaml` akan mengubah koneksi runtime app menjadi:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

### 6.2 Build dan jalankan

```sh
npm ci
npm run build
docker compose up --build -d
docker compose ps
```

Container `app` melakukan `php artisan migrate --force` saat startup. Seed data tidak dijalankan otomatis; jalankan sekali jika environment masih kosong:

```sh
docker compose exec app php artisan db:seed --force --no-interaction
docker compose exec app php artisan about
```

`Dockerfile` saat ini tidak menjalankan `npm ci` atau `npm run build`, sehingga dua perintah pertama pada blok di atas wajib dijalankan sebelum build image.

Untuk deployment production, jangan memasukkan `.env` berisi secret ke build context: `Dockerfile` memakai `COPY . .` dan belum memiliki `.dockerignore`. Pass secret melalui environment/secret manager runtime setelah image dibuat.

`compose.yaml` saat ini hanya meneruskan variabel database, cache, queue, dan Redis ke container `app`. Untuk login Keycloak dari container, injeksikan `KEYCLOAK_*` sebagai environment runtime melalui platform deployment/secret manager dan tambahkan deklarasi runtime yang diperlukan pada konfigurasi Compose.

Jika perlu melihat log:

```sh
docker compose logs -f app
```

Buka <http://127.0.0.1:8000/admin>.

### 6.3 Hentikan stack

```sh
docker compose down
```

Perintah berikut menghapus volume database dan Redis. Gunakan hanya untuk environment disposable:

```sh
docker compose down -v
```

---

## 7. Konfigurasi `.env`

### 7.1 Variabel wajib

`php artisan app:validate-environment` memeriksa variabel berikut:

| Variabel | Fungsi |
| --- | --- |
| `APP_KEY` | Enkripsi/session Laravel; dibuat dengan `key:generate`. |
| `KEYCLOAK_BASE_URL` | Origin Keycloak, tanpa path callback aplikasi. |
| `KEYCLOAK_REALM` | Realm Keycloak, default `umrah`. |
| `KEYCLOAK_CLIENT_ID` | ID client OIDC. |
| `KEYCLOAK_CLIENT_SECRET` | Secret confidential client. |
| `KEYCLOAK_REDIRECT_URI` | Callback, biasanya `${APP_URL}/auth/keycloak/callback`. |
| `KEYCLOAK_POST_LOGOUT_REDIRECT_URI` | URL setelah logout. |

Contoh local:

```dotenv
KEYCLOAK_BASE_URL=https://sso.example.com
KEYCLOAK_REALM=umrah
KEYCLOAK_CLIENT_ID=umrah-procurement
KEYCLOAK_CLIENT_SECRET=<CLIENT_SECRET>
KEYCLOAK_REDIRECT_URI=http://localhost:8000/auth/keycloak/callback
KEYCLOAK_POST_LOGOUT_REDIRECT_URI=http://localhost:8000
```

Setelah mengubah environment yang sudah pernah dicache:

```sh
php artisan config:clear
```

### 7.2 Variabel Keycloak opsional

```dotenv
KEYCLOAK_ISSUER=
KEYCLOAK_AUDIENCE=
KEYCLOAK_AUTHORIZATION_ENDPOINT=
KEYCLOAK_TOKEN_ENDPOINT=
KEYCLOAK_USERINFO_ENDPOINT=
KEYCLOAK_JWKS_URI=
KEYCLOAK_CLOCK_SKEW=60
KEYCLOAK_PROVISIONING_MODE=hybrid
KEYCLOAK_LOGOUT_REDIRECT=true
```

Jika `KEYCLOAK_ISSUER` kosong, aplikasi menggunakan `<KEYCLOAK_BASE_URL>/realms/<KEYCLOAK_REALM>`. Jika `KEYCLOAK_AUDIENCE` kosong, audience default adalah `KEYCLOAK_CLIENT_ID`.

Mode provisioning yang tersedia adalah `jit`, `pre-provisioned`, dan `hybrid`; default proyek adalah `hybrid`.

### 7.3 Logging, session, cache, dan queue

Default lokal dari `.env.example`:

```dotenv
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
```

Untuk production-like:

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Jangan commit `APP_KEY`, `DB_PASSWORD`, Redis credential, atau `KEYCLOAK_CLIENT_SECRET`. Di production gunakan secret manager dan `APP_DEBUG=false`.

---

## 8. Konfigurasi Keycloak

Di Keycloak:

1. Buat atau pilih realm yang sama dengan `KEYCLOAK_REALM`.
2. Buat client confidential untuk aplikasi.
3. Aktifkan **Standard Flow**.
4. Aktifkan PKCE dengan metode **S256**.
5. Tambahkan redirect URI yang sama persis dengan `KEYCLOAK_REDIRECT_URI`.
6. Allow-list post-logout URI yang sama dengan `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`.
7. Pastikan client secret sama dengan `.env`.
8. Pastikan issuer, audience, userinfo, dan JWKS dapat dijangkau server Laravel.

Alur login aplikasi:

1. Browser membuka `/auth/keycloak/redirect`.
2. Laravel membuat `state`, nonce, dan PKCE verifier di session.
3. Keycloak melakukan autentikasi.
4. Callback `/auth/keycloak/callback` menukar authorization code.
5. Laravel memvalidasi issuer, audience, token, userinfo, dan kecocokan subject.
6. User lokal dibuat atau diperbarui berdasarkan Keycloak `sub`.
7. User diarahkan ke `/admin` jika memiliki assignment kantor aktif.

Laravel tidak menganggap email sebagai identitas immutable. Identitas utama adalah Keycloak `sub`.

Validasi setelah konfigurasi:

```sh
php artisan config:clear
php artisan app:validate-environment --no-interaction
```

---

## 9. Bootstrap user dan office assignment

### 9.1 Aturan akses panel

User dapat masuk ke panel hanya jika semua syarat berikut terpenuhi:

- User aktif.
- Memiliki `UserAssignment` yang aktif.
- Assignment berada dalam rentang `valid_from` sampai `valid_until`.
- Office terkait aktif dan tidak disabled.
- Ada office aktif yang dipin di session.

User yang berhasil login ke Keycloak tetapi belum memiliki assignment aktif akan ditolak oleh panel. Ini adalah gate keamanan, bukan error login Keycloak.

### 9.2 Data contoh dari seeder

`OrganizationSeeder` membuat contoh:

- Office `JKT` — Kantor Pusat Jakarta.
- Office `SBY` — Kantor Regional Surabaya.
- User operasional Jakarta dan Surabaya.
- User procurement lintas kantor.
- Assignment dengan role Operasional atau Pengadaan.

User seeder tetap harus cocok dengan subject Keycloak yang datang saat login. Email pada seeder bukan password login lokal.

`DatabaseSeeder` juga membuat `test@example.com` sebagai row user aktif, tetapi baris tersebut bukan akun password lokal dan bukan pengganti assignment Keycloak.

### 9.3 Membuat assignment dari panel

Setelah ada user lokal dan admin yang bisa mengakses panel:

1. Buka **Umrah Operations → Assignments**.
2. Pilih user.
3. Pilih office.
4. Pilih role.
5. Isi branch, department, cost center, dan periode berlaku jika diperlukan.
6. Aktifkan assignment dan tandai primary bila sesuai.
7. Simpan.

Jika organisasi membutuhkan admin pertama, buat atau cocokkan user Keycloak terlebih dahulu, pastikan user mempunyai assignment aktif, lalu gunakan permission management dari role yang sesuai. Perintah Shield berikut hanya memberi super-admin role; perintah ini tidak menggantikan office assignment:

```sh
php artisan shield:super-admin --user=<LOCAL_USER_ID> --panel=admin --no-interaction
```

---

## 10. Role dan permission

Role yang dibuat oleh `RolePermissionSeeder`:

| Role | Fungsi umum |
| --- | --- |
| `Admin` | Operasi procurement, approval, master data, finance, user, dan role. |
| `Operasional` | Melihat, membuat request, dan menerima barang. |
| `Pengadaan` | Request, update, master data, dan receiving. |
| `Keuangan` | Finance, export, dan koreksi receipt. |
| `Manager` | Approval dan export. |
| `Manajemen` | Read dan export. |
| `Auditor` | Read dan export. |
| `Viewer` | Read-only. |

Permission utama menggunakan namespace `procurement`:

```text
procurement.view
procurement.create
procurement.update
procurement.delete
procurement.submit
procurement.approve
procurement.export
procurement.manage-master-data
procurement.manage-finance
procurement.receive
procurement.correct-receipt
procurement.manage-users
procurement.manage-roles
```

Permission tidak cukup tanpa assignment kantor aktif. Assignment menentukan konteks kantor, cabang, department, cost center, role, dan periode akses.

---

## 11. Struktur sidebar admin

Panel `/admin` memakai enam kelompok menu berikut:

### Procurement

- Requests
- Quotes
- Purchase Orders
- Invoices
- Distributions

### Approvals

- Approval Inbox
- Procurement Reviews

### Master Data

- Items
- Categories
- Units
- Variants
- Custom Fields
- Vendors
- Workflows
- Workflow Stages
- Workflow Versions

### Umrah Operations

- Pilgrims
- Umrah Batches
- Departure Batches
- Sample Shipments
- Assignments

### Organization & Finance

- Branches
- Offices
- Departments
- Cost Centers
- Budgets

### Settings

- Approver Mappings
- Approver Delegations
- Roles
- Activity Log

Menu yang tampil tetap bergantung pada permission dan policy user aktif.

---

## 12. Setup data bisnis pertama kali

Urutan berikut menghindari foreign key dan workflow yang belum siap.

### 12.1 Organization & Finance

1. Buat atau review **Offices**.
2. Buat **Branches** di dalam office.
3. Buat **Departments**.
4. Buat **Cost Centers**.
5. Buat atau review **Budgets**.
6. Buat **Assignments** untuk user dan role.

### 12.2 Master Data

1. Buat **Units**, misalnya PCS, SET, BOX, PACK.
2. Buat **Categories**.
3. Konfigurasi **Custom Fields** untuk category yang membutuhkan atribut tambahan.
4. Buat **Items**.
5. Buat **Variants** jika item memiliki ukuran/warna/jenis.
6. Buat **Vendors** dan referensikan item yang dapat disediakan.

Seeder menyediakan contoh unit, category, item, variant, dan vendor agar alur dapat diuji setelah seed.

### 12.3 Workflow approval

Gunakan menu berikut untuk konfigurasi:

- **Workflows** — definisi alur.
- **Workflow Stages** — urutan tahap.
- **Workflow Versions** — versi aktif yang dipakai.
- **Approver Mappings** — mapping office/role ke approver.
- **Approver Delegations** — delegasi approver sementara.

Seeder membuat standard workflow dengan tahap Procurement Review dan Finance Approval. Review mapping sebelum dipakai untuk data production.

### 12.4 Umrah data

1. Buat atau review **Umrah Batches**.
2. Buat **Departure Batches** jika digunakan oleh alur keberangkatan.
3. Daftarkan **Pilgrims**.
4. Pastikan batch yang diwajibkan category sudah dipilih saat membuat request.

---

## 13. Alur kerja procurement end-to-end

### Tahap 1 — Buat request

1. Buka **Procurement → Requests**.
2. Buat draft request.
3. Isi requester dan konteks organisasi: office, branch, department, cost center.
4. Isi title, required date, priority, reason, dan notes.
5. Pilih category dan departure/umrah batch jika diwajibkan category.
6. Tambahkan satu atau lebih item.
7. Isi quantity positif dan unit price tidak negatif.
8. Isi custom fields yang diwajibkan category.
9. Tambahkan attachment bila diperlukan.
10. Simpan sebagai draft.

Request list terutama menampilkan request berstatus Draft dan Returned. Draft atau Returned yang valid dapat di-submit.

### Tahap 2 — Submit dan procurement review

Gunakan action **Submit**. Sistem memvalidasi:

- Category masih aktif.
- Batch yang diwajibkan sudah dipilih.
- Reason tersedia.
- Minimal satu item ada.
- Quantity valid.
- Unit price valid.
- Custom field wajib terisi.

Setelah submit, request masuk ke proses **Procurement Reviews**. Procurement dapat meninjau detail dan menyiapkan quotation.

### Tahap 3 — Quotation

1. Buka **Procurement → Quotes**.
2. Buat quote untuk request yang dapat diproses.
3. Pilih vendor.
4. Isi quote number, currency, tanggal quote, dan tanggal berlaku.
5. Isi harga per item, discount, tax, shipping, notes, dan attachment.
6. Gunakan **Bandingkan** untuk membandingkan quote.
7. Gunakan **Rekomendasikan vendor** jika vendor pilihan sudah ditentukan.
8. Gunakan **Serahkan ke approval** untuk memvalidasi recommendation; final handoff ke approval dilakukan dari **Procurement Reviews**.

### Tahap 4 — Approval

1. Buka **Approvals → Approval Inbox**.
2. Buka task pending.
3. Review request, quote, budget, dan konteks kantor.
4. Pilih salah satu action:
   - **Approve** — lanjut ke tahap berikutnya.
   - **Reject** — hentikan request sesuai alasan.
   - **Return** — kembalikan untuk perbaikan.
5. Isi approval notes bila diminta.

SLA approval diproses oleh command terjadwal setiap lima menit.

### Tahap 5 — Purchase Order

Buka **Procurement → Purchase Orders** untuk memantau PO yang tersedia atau terbentuk dari proses bisnis. Action yang tampil bergantung pada status PO dan permission, misalnya view, edit revision, approve, print, atau delete draft.

Jangan menganggap semua request otomatis mempunyai PO. Pastikan proses quotation dan approval selesai serta data PO tersedia.

### Tahap 6 — Receiving

Receiving dilakukan pada PO yang approved/issued:

1. Buka detail PO.
2. Buka relation **Goods receipts**.
3. Klik **Record receipt**.
4. Isi quantity yang diterima.
5. Pastikan quantity tidak melebihi sisa quantity PO.
6. Sertakan bukti jika proses meminta attachment.
7. Koreksi receipt memerlukan `procurement.correct-receipt`; resource Goods receipts saat ini tidak menampilkan action koreksi pada tabel.

Receiver harus mempunyai assignment aktif dalam scope PO.

### Tahap 7 — Invoice dan payment

1. Buka **Procurement → Invoices**.
2. Buat invoice untuk PO approved/issued.
3. Isi nomor, tanggal jatuh tempo, total, dan line invoice.
4. Pastikan invoice match dengan PO berdasarkan line, quantity, dan amount.
5. Upload bukti invoice pada storage private.
6. Saat matching berhasil, service otomatis menyetujui invoice.
7. Catat payment melalui relation **Payments** dan simpan bukti pembayaran.

### Tahap 8 — Distribution

Gunakan **Procurement → Distributions** setelah stock diterima. Pilih mode distribusi yang tersedia, misalnya batch atau individual, kemudian:

1. Pilih item/stock yang tersedia.
2. Pilih batch atau pilgrim tujuan.
3. Isi quantity alokasi.
4. Pastikan allocation tidak melebihi stock.
5. Simpan evidence bila diwajibkan.

### Tahap 9 — Sample shipment

Gunakan **Umrah Operations → Sample Shipments** untuk pengiriman sample antar-office. Setelah shipment diterima, lakukan konfirmasi receipt dan simpan foto atau tanda tangan jika diminta oleh form.

---

## 14. Activity Log dan export

Buka **Settings → Activity Log** atau route `/admin/activity-logs`.

- Melihat log membutuhkan `procurement.view`.
- Export membutuhkan `procurement.export`.
- Data log mencatat aktivitas model/admin sesuai konfigurasi Filament Logger.

Command maintenance logger tersedia:

```sh
php artisan activitylog:clean --no-interaction
php artisan filament-logger:prune --no-interaction
php artisan filament-logger:prune-exports --no-interaction
php artisan filament-logger:send-alert-digests --no-interaction
```

Jadwal retention harus disesuaikan dengan kebijakan audit organisasi sebelum diaktifkan di production.

---

## 15. File attachment

Attachment aplikasi memakai upload private dan mendukung tipe file yang dikonfigurasi service, termasuk PDF, dokumen Office, spreadsheet, gambar, CSV, dan TXT. Batas default service adalah 10 MB.

Download memakai route terautentikasi:

```text
GET /attachments/{attachment}/download
```

### Catatan konfigurasi repository saat ini

`AttachmentService` menggunakan disk `private` secara default, sementara `config/filesystems.php` pada tree ini hanya mendefinisikan disk standar `local`, `public`, dan `s3`. Sebelum memakai upload attachment di environment baru, verifikasi dan definisikan disk `private`, misalnya:

```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => false,
    'throw' => true,
],
```

Jangan menjadikan attachment sensitif sebagai file public. Jika aplikasi membutuhkan file public non-sensitif, buat symbolic link dengan:

```sh
php artisan storage:link
```

---

## 16. Worker dan scheduler

### Queue worker

Dengan queue database lokal:

```sh
php artisan queue:work
```

Dengan Redis, pastikan `.env` memakai `QUEUE_CONNECTION=redis`, `REDIS_HOST` benar, lalu jalankan worker yang sama.

### Scheduler

`routes/console.php` menjadwalkan `approvals:process-sla` setiap lima menit dengan pencegahan overlap dan satu server.

Development:

```sh
php artisan schedule:work
```

Production biasanya memakai satu scheduler process yang menjalankan scheduler Laravel secara terus-menerus, atau cron yang memanggil `schedule:run` sesuai standar deployment.

Jalankan command SLA manual untuk diagnosis:

```sh
php artisan approvals:process-sla --no-interaction
```

---

## 17. Command reference

### Informasi dan diagnosis

```sh
php artisan about
php artisan route:list
php artisan schedule:list
php artisan migrate:status --no-interaction
php artisan app:validate-environment --no-interaction
```

### Database

```sh
php artisan migrate --no-interaction
php artisan migrate --seed --no-interaction
php artisan db:seed --no-interaction
php artisan migrate:fresh --seed --no-interaction
```

`migrate:fresh` menghapus seluruh tabel pada database aktif. Jangan menjalankannya pada database production.

### Cache/config

```sh
php artisan config:clear
php artisan config:cache
php artisan cache:clear
php artisan optimize
```

Gunakan `config:clear` ketika mengecek perubahan `.env`. Gunakan `config:cache` atau `optimize` setelah environment final di deployment.

### Role/permission

```sh
php artisan permission:show
php artisan permission:cache-reset
php artisan shield:super-admin --help
```

---

## 18. Testing dan quality check

Test suite memakai in-memory SQLite, array cache, sync queue, dan driver session test sehingga tidak memerlukan PostgreSQL/Redis eksternal untuk test dasar.

Jalankan test:

```sh
composer test
```

Atau:

```sh
php artisan test --compact
```

Build asset:

```sh
npm run build
```

Format PHP yang berubah:

```sh
vendor/bin/pint --dirty --format agent
```

Untuk perubahan tertentu, jalankan test file paling sempit terlebih dahulu:

```sh
php artisan test --compact tests/Feature/<NamaTest>.php
```

CI proyek juga menjalankan install dependency dari lockfile, build frontend, validasi Composer, lint PHP, Pint, route/about checks, config cache, environment validation, dan feature tests terhadap PostgreSQL 16 + Redis 7.

---

## 19. Troubleshooting

### `database/database.sqlite` tidak ditemukan

Buat file sebelum migrasi:

```sh
touch database/database.sqlite
php artisan migrate --seed --no-interaction
```

Pastikan PHP memiliki ekstensi `pdo_sqlite`.

### `app:validate-environment` gagal

Periksa variabel wajib, terutama:

- `APP_KEY`
- `KEYCLOAK_BASE_URL`
- `KEYCLOAK_REALM`
- `KEYCLOAK_CLIENT_ID`
- `KEYCLOAK_CLIENT_SECRET`
- `KEYCLOAK_REDIRECT_URI`
- `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`

Setelah perubahan `.env`:

```sh
php artisan config:clear
php artisan app:validate-environment --no-interaction
```

### Login Keycloak berhasil tetapi `/admin` ditolak

Periksa:

1. User lokal ditemukan berdasarkan Keycloak `sub` yang benar.
2. `is_active` user bernilai aktif.
3. Ada assignment aktif untuk user.
4. `valid_from`/`valid_until` mencakup tanggal hari ini.
5. Office assignment aktif dan tidak disabled.
6. Role assignment memiliki permission yang diperlukan.

Email seed saja tidak membuat password lokal dan tidak menjamin assignment cocok dengan subject Keycloak Anda.

### Callback Keycloak mismatch

Redirect URI harus sama persis antara Keycloak dan `.env`, termasuk:

- scheme (`http`/`https`),
- host,
- port,
- path `/auth/keycloak/callback`,
- trailing slash.

Untuk local, gunakan satu bentuk host secara konsisten: `localhost` atau `127.0.0.1`.

### MySQL tidak bisa tersambung

Periksa database, user, port, dan host:

```sh
php artisan db:show
```

Jika MySQL berjalan dalam container yang mempublish port ke host, gunakan host port dari komputer untuk Artisan yang berjalan di host. Jika aplikasi berjalan di dalam Docker, gunakan hostname service/container, bukan `::1` atau `127.0.0.1`.

### Docker app tidak bisa connect ke database

Pastikan:

```sh
docker compose ps
docker compose logs postgres
docker compose logs redis
docker compose logs app
```

`app` harus memakai `postgres` dan `redis` sebagai host internal. Tunggu health check PostgreSQL dan Redis menjadi healthy.

### Data belum muncul setelah deploy

Container app otomatis migrate, tetapi tidak otomatis seed. Jalankan hanya untuk environment baru:

```sh
docker compose exec app php artisan db:seed --force --no-interaction
```

### Queue atau approval SLA tidak bergerak

Jalankan worker dan scheduler sebagai process terpisah:

```sh
php artisan queue:work
php artisan schedule:work
```

Periksa `QUEUE_CONNECTION` dan log Laravel.

### Upload attachment gagal karena disk

Pastikan disk `private` sudah didefinisikan di `config/filesystems.php`, directory storage dapat ditulis, lalu bersihkan config cache:

```sh
php artisan config:clear
```

### Asset frontend tidak ikut berubah

Build ulang asset:

```sh
npm run build
```

Untuk development gunakan `composer dev` agar Vite berjalan bersama server Laravel.

---

## 20. Checklist production

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` memakai HTTPS.
- [ ] Redirect URI Keycloak memakai HTTPS dan allow-list yang tepat.
- [ ] Secret tidak ada di Git, Dockerfile, image layer, atau log.
- [ ] `APP_KEY` stabil dan disimpan di secret manager.
- [ ] Database memakai user khusus aplikasi dengan privilege minimum.
- [ ] PostgreSQL 16 dan Redis 7 tersedia serta health check dipantau.
- [ ] `QUEUE_CONNECTION=redis` dan worker selalu hidup.
- [ ] Scheduler berjalan satu instance untuk command SLA.
- [ ] Disk private attachment tersedia dan tidak diekspos sebagai public asset.
- [ ] Backup database dan retention audit log diuji.
- [ ] Minimal satu admin memiliki office assignment aktif.
- [ ] `php artisan config:cache` dijalankan hanya setelah semua environment benar.
- [ ] `php artisan app:validate-environment --no-interaction` berhasil.
- [ ] `php artisan about` dan health endpoint berhasil.
- [ ] `npm run build` berhasil.
- [ ] Test suite dan Pint berhasil pada commit yang akan dideploy.

---

## 21. File rujukan utama

- `README.md` — ringkasan arsitektur dan setup.
- `.env.example` — daftar environment variable dan default lokal.
- `compose.yaml` — stack Docker PostgreSQL/Redis/app.
- `Dockerfile` — build dan startup image app.
- `composer.json` — script `setup`, `dev`, dan `test`.
- `routes/web.php` — route health, Keycloak, logout, office context, dan attachment.
- `database/seeders/DatabaseSeeder.php` — urutan seed utama.
- `database/seeders/RolePermissionSeeder.php` — role dan permission.
- `database/seeders/OrganizationSeeder.php` — sample organisasi, user, dan assignment.
- `config/keycloak.php` — default konfigurasi OIDC.
- `app/Console/Commands/ValidateEnvironment.php` — validasi environment tanpa mencetak secret.
- `app/Filament/Resources` — resource admin per modul.

Jika perilaku aplikasi berubah, update panduan ini bersama perubahan konfigurasi, seed, route, atau workflow.
