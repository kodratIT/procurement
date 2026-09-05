# Umrah Procurement — Panduan Menggunakan Fitur Secara Berurutan

Panduan ini menjelaskan **apa yang harus dilakukan lebih dulu, menu mana yang dibuka, apa yang diisi, dan hasil yang diharapkan**. Ikuti urutan dari atas ke bawah.

Panduan setup server ada di [PROCUREMENT-SETUP-AND-USAGE.md](./PROCUREMENT-SETUP-AND-USAGE.md). Dokumen ini fokus pada penggunaan fitur di admin panel.

---

## 1. Gambaran alur besar

```text
Login Keycloak
  ↓
Pilih kantor aktif
  ↓
Organisasi + Budget
  ↓
Role + Assignment user
  ↓
Batch umrah + jamaah
  ↓
Master data procurement
  ↓
Workflow approval
  ↓
Purchase Request
  ↓
Procurement Review
  ↓
Quotation + rekomendasi vendor
  ↓
Approval Inbox
  ↓
Purchase Order
  ↓
Goods Receipt
  ↓
Invoice Matching
  ↓
Payment
  ↓
Distribution ke batch/jamaah
```

Tidak semua request melewati semua tahap. **Categories** memiliki pengaturan wajib atau opsional untuk batch, jamaah, vendor, quotation, rekomendasi vendor, receipt, invoice, dan purchase order. Jika suatu pengaturan tidak aktif, tahap tersebut dapat dilewati.

**Sample Shipments** adalah alur terpisah untuk mengirim sample antar-kantor.

---

## 2. Cara membaca tampilan Filament

Setiap menu biasanya memiliki:

- **New/Create** — membuat data baru.
- **Edit** — mengubah data.
- **View** — melihat detail.
- **Delete** — menghapus jika policy mengizinkan.
- **Filter** — menyaring daftar.
- **Search** — mencari berdasarkan kolom yang tersedia.
- **Actions** — action tambahan pada baris, biasanya berada di sisi kanan tabel.
- **Export** — export data yang dipilih jika role memiliki permission export.

Action dapat tidak terlihat karena status record, office aktif, assignment, atau permission user. Jangan menganggap tombol yang tidak terlihat sebagai error sebelum memeriksa role dan assignment.

---

## 3. Langkah 0 — Login dan pilih kantor aktif

### 3.1 Login

1. Buka `/admin`.
2. Klik tombol login Keycloak.
3. Login pada halaman Keycloak.
4. Setelah callback berhasil, aplikasi mengarahkan Anda kembali ke admin panel.

Aplikasi tidak memakai password lokal. Email seed juga bukan password login.

### 3.2 Pastikan office aktif

Setelah masuk, lihat selector konteks di header panel:

- Jika hanya memiliki satu assignment, nama office/branch/department/role ditampilkan.
- Jika memiliki beberapa assignment, pilih konteks dari dropdown **Active access context**.
- Jika tombol **Confirm mutations** muncul, tekan tombol tersebut sebelum membuat atau mengubah data di office non-primary.

Semua data office-scoped yang dibuat setelah itu menggunakan konteks kantor aktif. Salah memilih office dapat membuat data tidak terlihat pada daftar office lain.

### Hasil yang benar

Anda melihat dashboard/admin panel dan menu sesuai permission assignment aktif.

### Jika gagal

Periksa user aktif, assignment aktif, periode `Valid from`/`Valid until`, office aktif, dan role assignment.

---

## 4. Langkah 1 — Siapkan Organization & Finance

Lakukan langkah ini setelah minimal satu admin memiliki assignment aktif. Untuk data bisnis, siapkan organisasi dan budget sebelum membuat assignment user lain atau transaksi procurement.

Menu: **Organization & Finance**

### 4.1 Buat Offices

1. Buka **Offices**.
2. Klik **New**.
3. Isi nama office dan kode yang diminta.
4. Pastikan office berstatus aktif.
5. Klik **Create/Save**.

Contoh data seed:

- `JKT` — Kantor Pusat Jakarta.
- `SBY` — Kantor Regional Surabaya.

### 4.2 Buat Branches

1. Buka **Branches**.
2. Klik **New**.
3. Pilih **Kantor**.
4. Isi kode dan nama branch.
5. Aktifkan branch.
6. Simpan.

Branch harus berada di office yang benar. Branch nonaktif tidak dipakai pada pilihan transaksi baru.

### 4.3 Buat Departments

1. Buka **Departments**.
2. Klik **New**.
3. Pilih office.
4. Pilih branch bila department dibatasi ke branch tertentu.
5. Isi kode dan nama department.
6. Aktifkan dan simpan.

### 4.4 Buat Cost Centers

1. Buka **Cost Centers**.
2. Klik **New**.
3. Pilih office.
4. Isi kode dan nama cost center.
5. Aktifkan dan simpan.

Cost center dipakai pada request dan budget. Pastikan cost center berada di office yang sama dengan konteks transaksi.

### 4.5 Buat Budgets

1. Buka **Budgets**.
2. Klik **New**.
3. Pilih **Kantor**.
4. Pilih **Cost center** dari office tersebut.
5. Isi **Year**.
6. Isi **Amount**.
7. Pilih status **Active** jika budget siap dipakai.
8. Simpan.

Satu kombinasi office, cost center, dan year tidak boleh diduplikasi.

### Hasil yang benar

Office, branch, department, cost center, dan budget tersedia sebagai pilihan pada form berikutnya.

---

## 5. Langkah 2 — Siapkan Role dan Assignment user

Menu role: **Settings → Roles**  
Menu assignment: **Umrah Operations → Assignments**

### 5.1 Pastikan role tersedia

Role yang dibuat oleh seed utama:

- **Admin** — pengelolaan luas, termasuk user, role, master data, finance, dan approval.
- **Operasional** — membuat request dan menerima barang.
- **Pengadaan** — mengelola request/review, master data, dan receiving.
- **Keuangan** — mengelola finance, export, dan koreksi receipt.
- **Manager** — melakukan approval.
- **Manajemen** — melihat dan export.
- **Auditor** — melihat dan export.
- **Viewer** — read-only.

Jika perlu membuat atau mengubah role, buka **Settings → Roles**, lalu gunakan action create/edit dari Filament Shield.

### 5.2 Buat assignment

User lokal biasanya muncul setelah user berhasil login melalui Keycloak. Setelah user ada:

1. Buka **Umrah Operations → Assignments**.
2. Klik **New**.
3. Pilih **User**.
4. Pilih **Kantor**.
5. Pilih **Role**.
6. Pilih branch, department, dan cost center bila diperlukan.
7. Pastikan **Active** menyala.
8. Isi **Valid from**.
9. Isi **Valid until** jika assignment memiliki batas waktu.
10. Tandai **Primary assignment** untuk konteks utama user.
11. Simpan.

### 5.3 Assignment dengan beberapa office

Jika user bekerja di beberapa office:

1. Buat satu assignment untuk setiap office.
2. Tandai hanya konteks utama sebagai primary bila memang hanya ada satu konteks utama.
3. Saat bekerja, user berpindah melalui selector konteks di header.
4. Pastikan user memilih office yang benar sebelum mutasi.

### Hasil yang benar

User dapat masuk ke panel dan melihat menu sesuai role serta office assignment aktif.

### Hal yang sering membingungkan

Role saja tidak cukup. User tanpa assignment kantor aktif tetap tidak dapat memakai panel.

---

## 6. Langkah 3 — Siapkan data Umrah Operations

Menu: **Umrah Operations**

### 6.1 Buat Umrah Batches

1. Buka **Umrah Batches**.
2. Klik **New**.
3. Pilih **Kantor**.
4. Isi code dan name batch.
5. Isi **Departure date**.
6. Isi **Return date** bila sudah diketahui.
7. Isi **Capacity**.
8. Isi jumlah jamaah jika diperlukan.
9. Pilih status batch yang tersedia, misalnya Planned atau Open.
10. Pastikan **Aktif** menyala.
11. Simpan.

Jamaah baru hanya dapat dikaitkan ke batch yang aktif dan tersedia untuk pendaftaran.

### 6.2 Buat Departure Batches bila dipakai procurement

1. Buka **Departure Batches**.
2. Klik **New**.
3. Isi code dan name.
4. Isi tanggal keberangkatan dan kepulangan.
5. Isi capacity.
6. Pilih status: Planned, Open, Closed, atau Departed.
7. Aktifkan dan simpan.

`Departure Batch` adalah pilihan pada request ketika category mengharuskan batch keberangkatan. Jangan menyamakan record ini dengan `Umrah Batch`; keduanya adalah resource berbeda.

### 6.3 Daftarkan Pilgrims

1. Buka **Pilgrims**.
2. Klik **New**.
3. Pilih **Batch Umrah** yang aktif dan masih Planned/Open.
4. Isi nama lengkap.
5. Isi nomor paspor.
6. Isi nomor telepon bila ada.
7. Pilih status jamaah.
8. Aktifkan dan simpan.

Nomor paspor harus unik di dalam batch yang dipilih.

### 6.4 Import data bila diperlukan

**Umrah Batches** dan **Pilgrims** menyediakan action import CSV jika role memiliki permission create.

Sebelum import:

1. Siapkan CSV sesuai kolom yang diminta importer.
2. Pastikan office aktif sudah benar.
3. Pastikan tanggal dan status valid.
4. Import data.
5. Periksa kembali hasil import di tabel.

### Hasil yang benar

Batch dan jamaah tersedia sebagai pilihan pada request, distribution, dan receipt individual.

---

## 7. Langkah 4 — Siapkan Master Data

Menu: **Master Data**

Ikuti urutan ini:

```text
Units
  ↓
Categories
  ↓
Custom Fields
  ↓
Items
  ↓
Variants
  ↓
Vendors
```

### 7.1 Buat Units

1. Buka **Units**.
2. Klik **New**.
3. Isi code, name, dan symbol.
4. Aktifkan unit.
5. Simpan.

Contoh: PCS, SET, BOX, PACK.

### 7.2 Buat Categories

1. Buka **Categories**.
2. Klik **New**.
3. Isi code dan name.
4. Pilih type.
5. Isi description bila perlu.
6. Atur flag yang wajib.
7. Isi **Workflow reference** bila category memakai workflow tertentu.
8. Isi **Number template** bila penomoran khusus dipakai.
9. Aktifkan dan simpan.

Flag category yang tersedia:

| Flag | Dampak pada alur |
| --- | --- |
| Wajib batch keberangkatan | Request harus memilih Departure Batch. |
| Wajib terkait jamaah | Request harus memiliki konteks jamaah sesuai aturan proses. |
| Wajib vendor | Vendor harus tersedia pada proses procurement. |
| Wajib quotation | Quotation harus dibuat sebelum proses dilanjutkan. |
| Wajib alasan rekomendasi vendor | Rekomendasi vendor harus memiliki alasan. |
| Wajib bukti rekomendasi vendor | Rekomendasi vendor harus memiliki bukti attachment. |
| Wajib penerimaan | Barang/jasa harus memiliki receipt. |
| Wajib invoice | Invoice harus dicatat. |
| Wajib purchase order | PO harus tersedia. |

> **Catatan:** flag **Wajib terkait jamaah** tersedia pada konfigurasi category, tetapi form **Requests** saat ini tidak menampilkan selector `pilgrim_id`. Jangan mengaktifkan flag ini untuk transaksi production sebelum flow category tersebut dikonfirmasi.

Mulai dengan category yang sederhana untuk latihan, lalu aktifkan aturan wajib sesuai kebijakan bisnis.

### 7.3 Buat Custom Fields

1. Buka **Custom Fields**.
2. Klik **New**.
3. Pilih **Kategori**.
4. Isi **key** menggunakan lowercase dan underscore, contoh `room_type`.
5. Isi label yang akan dilihat user.
6. Pilih tipe field.
7. Isi urutan tampil.
8. Aktifkan **Wajib** jika harus diisi.
9. Isi opsi jika tipe field berupa dropdown/radio/relasi/varian.
10. Isi nilai default, minimum, atau maksimum jika diperlukan.
11. Atur **Kondisi tampil** bila field hanya muncul pada kondisi tertentu.
12. Pilih tahap field dapat diubah: Draft, Review pengadaan, atau Approval.
13. Simpan.
14. Gunakan action **Preview** untuk memastikan field tampil sesuai harapan.

Custom field aktif akan muncul otomatis pada section **Field kategori** di form request.

### 7.4 Buat Items

1. Buka **Items**.
2. Klik **New**.
3. Isi **SKU**.
4. Isi nama item.
5. Pilih category.
6. Pilih unit.
7. Isi description bila perlu.
8. Isi reference price dan currency bila ada.
9. Isi specifications bila perlu.
10. Aktifkan dan simpan.

Item nonaktif tidak seharusnya dipakai sebagai pilihan transaksi baru.

### 7.5 Buat Variants

1. Buka **Variants**.
2. Klik **New**.
3. Pilih item.
4. Pilih tipe variasi: Ukuran, Warna, atau Bahan.
5. Isi kode, nama, dan value.
6. Isi atribut tambahan bila perlu.
7. Aktifkan dan simpan.

Variant hanya muncul untuk item yang dipilih.

### 7.6 Buat Vendors

1. Buka **Vendors**.
2. Klik **New**.
3. Isi identitas dan informasi vendor yang diminta form.
4. Hubungkan item yang dapat disediakan vendor bila tersedia pada form.
5. Aktifkan dan simpan.

### Hasil yang benar

Saat membuat request, user dapat memilih category, item, unit, variant, custom field, dan vendor yang aktif.

---

## 8. Langkah 5 — Siapkan workflow approval

Menu: **Master Data → Workflows**, **Workflow Stages**, dan **Workflow Versions**  
Menu mapping: **Settings → Approver Mappings** dan **Approver Delegations**

Untuk workflow baru, ikuti urutan berikut:

### 8.1 Buat Workflow

1. Buka **Workflows**.
2. Klik **New**.
3. Isi code.
4. Isi name.
5. Isi description.
6. Aktifkan workflow.
7. Simpan.

### 8.2 Buat Workflow Version

1. Buka **Workflow Versions**.
2. Klik **New**.
3. Pilih workflow.
4. Isi version number.
5. Pilih status, biasanya mulai dari Draft.
6. Isi `effective_from` dan `effective_until` jika diperlukan.
7. Simpan.

### 8.3 Buat Workflow Stages

1. Buka **Workflow Stages**.
2. Klik **New**.
3. Pilih workflow version.
4. Isi sequence mulai dari 1.
5. Isi nama stage.
6. Pilih step type.
7. Pilih approval mode, biasanya sequential.
8. Isi resolver type dan required permission jika diperlukan.
9. Atur apakah stage wajib.
10. Isi SLA minutes bila stage memiliki batas waktu.
11. Simpan.

Jika stage mempunyai kondisi, buka relation **Conditions** pada stage dan tambahkan aturan yang dibutuhkan.

### 8.4 Buat Approver Mappings

1. Buka **Approver Mappings**.
2. Klik **New**.
3. Pilih workflow step.
4. Pilih resolver, role, atau specific user sesuai desain workflow.
5. Pilih office dan scope branch/department/cost center bila diperlukan.
6. Atur **Scope source**.
7. Atur fallback jika approver tidak ditemukan.
8. Atur priority.
9. Pastikan **Allow self-approval** hanya aktif jika memang diizinkan.
10. Aktifkan mapping dan simpan.

### 8.5 Buat Approver Delegations bila ada pengganti

1. Buka **Approver Delegations**.
2. Klik **New**.
3. Pilih **Original approver**.
4. Pilih **Delegate**.
5. Isi Valid from dan Valid until.
6. Isi alasan.
7. Aktifkan dan simpan.

### Hasil yang benar

Pada tahap review, action **Preview approval** menampilkan workflow dan approver yang akan dipakai. Jangan mengaktifkan workflow sebelum mapping approver diuji.

Seeder sudah menyediakan standard workflow dengan tahap Procurement Review dan Finance Approval. Review mapping hasil seed sebelum dipakai untuk transaksi production.

---

## 9. Langkah 6 — Buat Purchase Request

Menu: **Procurement → Requests**

### 9.1 Isi konteks organisasi

1. Klik **New**.
2. Periksa **Pengaju**.
3. Periksa **Kantor aktif**, **Cabang aktif**, dan **Departemen aktif**.
4. Pilih **Cost center** yang sesuai.
5. Pilih **Kategori**.
6. Pilih **Batch keberangkatan** jika category mewajibkannya.

Konteks office, branch, dan department mengikuti assignment/kantor aktif. Field konteks yang dikunci tidak boleh dipaksa diganti dari form.

### 9.2 Isi detail draft

Isi:

- **Judul**.
- **Tanggal kebutuhan**.
- **Priority**: Rendah, Normal, Tinggi, atau Mendesak.
- **Alasan/kebutuhan** — wajib.
- **Catatan** bila ada.

### 9.3 Tambahkan item

Pada section **Item yang diminta**:

1. Pilih item.
2. Pilih satuan setelah item tersedia.
3. Pilih variant jika item mempunyai variant.
4. Isi quantity lebih besar dari 0.
5. Isi estimasi harga satuan minimal 0.
6. Isi nama bebas hanya jika diperlukan.
7. Isi description, specifications, dan catatan item.
8. Tambahkan baris lain bila request memiliki beberapa item.

### 9.4 Isi field kategori dan attachment

1. Isi section **Field kategori** yang muncul otomatis.
2. Isi semua field wajib.
3. Tambahkan attachment pada **Lampiran** bila diperlukan.
4. Simpan sebagai draft.

### Hasil yang benar

Request muncul di **Requests** dengan status Draft dan memiliki nomor PR setelah nomor dibuat oleh sistem.

### Validasi sebelum submit

Pastikan:

- Category aktif.
- Batch wajib sudah dipilih.
- Alasan terisi.
- Minimal satu item ada.
- Quantity valid.
- Harga satuan tidak negatif.
- Custom field wajib terisi.

---

## 10. Langkah 7 — Submit request

Di tabel **Requests**:

1. Cari request berstatus Draft.
2. Buka menu action pada baris.
3. Klik **Submit**.
4. Konfirmasi.

Request yang dikembalikan oleh approver/reviewer akan berstatus Returned dan kembali dapat diperbaiki. Setelah diperbaiki, ulangi action **Submit**.

### Hasil yang benar

Request tidak lagi tampil pada daftar Draft/Returned dan masuk ke antrean **Procurement Reviews** dengan status Submitted.

---

## 11. Langkah 8 — Procurement Review

Menu: **Approvals → Procurement Reviews**

Menu ini menampilkan request yang sesuai dengan scope assignment reviewer.

### 11.1 Mulai review

1. Buka request berstatus **Submitted**.
2. Klik **View** untuk membaca detail.
3. Periksa requester, office, branch, department, category, total, item, field dinamis, dan lampiran.
4. Klik **Edit** bila perlu melakukan koreksi yang diizinkan.
5. Isi **Alasan koreksi** jika mengubah item atau field.
6. Jika sudah siap, klik **Teruskan review**.
7. Isi catatan review bila diperlukan.
8. Konfirmasi.

Status request berubah menjadi **Procurement Review**.

### 11.2 Jika request perlu dikembalikan

1. Pada action request, klik **Kembalikan**.
2. Isi **Alasan pengembalian**.
3. Konfirmasi.

Requester memperbaiki request dari menu **Requests**, lalu melakukan **Submit** lagi.

### 11.3 Preview workflow

Sebelum mengirim ke approval:

1. Klik **Preview approval**.
2. Periksa workflow, version, stage, approver, dan scope.
3. Jika approver tidak ditemukan atau stage tidak sesuai, perbaiki workflow/mapping terlebih dahulu.

### Hasil yang benar

Request berstatus Procurement Review dan memiliki data yang siap diproses ke quotation atau approval.

---

## 12. Langkah 9 — Buat dan bandingkan Quotes

Menu: **Procurement → Quotes**

Quotation dapat dibuat untuk request berstatus Submitted atau Procurement Review, sesuai scope dan permission procurement.

### 12.1 Buat quotation

1. Klik **New**.
2. Pilih **PR**.
3. Pilih **Vendor** aktif.
4. Isi nomor quotation.
5. Isi currency, default biasanya IDR.
6. Isi tanggal penawaran dan tanggal berlaku.
7. Isi diskon, pajak, dan biaya pengiriman.
8. Isi catatan vendor.
9. Pada **Harga per item PR**, tambahkan semua item request.
10. Isi quantity dan harga satuan setiap item.
11. Upload lampiran quotation/bukti bila ada.
12. Simpan.

Quotation harus mencakup seluruh item request jika akan direkomendasikan untuk approval.

### 12.2 Bandingkan quotation

1. Pada tabel Quotes, pilih salah satu quotation dari PR.
2. Klik **Bandingkan**.
3. Bandingkan vendor, harga, line item, dan total.
4. Tutup modal dengan **Tutup**.

### 12.3 Rekomendasikan vendor

1. Klik **Rekomendasikan vendor** pada quotation terpilih.
2. Isi alasan rekomendasi jika diminta atau jika category mewajibkannya.
3. Pastikan bukti rekomendasi tetap terlampir jika diwajibkan category.
4. Konfirmasi.

### 12.4 Serahkan ke approval dari quote

1. Klik **Serahkan ke approval** pada quotation yang sudah direkomendasikan.
2. Konfirmasi.

Action ini memeriksa bahwa recommendation tersedia dan quotation mencakup item request. Untuk membuat approval instance, lanjutkan ke langkah berikutnya dari **Procurement Reviews**.

### Hasil yang benar

PR memiliki quotation, recommendation vendor, dan data perbandingan yang siap digunakan reviewer sebelum handoff approval.

---

## 13. Langkah 10 — Serahkan request ke approval

Kembali ke **Approvals → Procurement Reviews**.

1. Buka request berstatus **Procurement Review**.
2. Periksa quotation dan recommendation vendor.
3. Klik **Preview approval** sekali lagi.
4. Klik **Serahkan ke approval**.
5. Konfirmasi.

Sistem membuat approval instance dan mengubah status request menjadi **Pending Approval**.

Jika action gagal, biasanya workflow belum punya approver yang sesuai, quotation belum lengkap, recommendation belum ada, atau category memiliki bukti wajib yang belum terpenuhi.

---

## 14. Langkah 11 — Proses Approval Inbox

Menu: **Approvals → Approval Inbox**

Hanya task approval yang masih pending ditampilkan.

### 14.1 Approve

1. Buka task pending.
2. Periksa nomor PR, tahap, workflow, kantor, requester, category, total, item, quotation, recommendation, dan perubahan PR.
3. Klik **Approve**.
4. Konfirmasi jika diminta.

### 14.2 Reject

1. Klik **Reject**.
2. Isi catatan/alasan pada field **Catatan**.
3. Konfirmasi.

### 14.3 Return

1. Klik **Return**.
2. Isi catatan yang menjelaskan perbaikan yang diperlukan.
3. Konfirmasi.

### Hasil approval

- Jika masih ada tahap berikutnya, request tetap berjalan ke approver berikutnya.
- Jika semua tahap selesai, request berstatus Approved.
- Jika ditolak, request berstatus Rejected.
- Jika dikembalikan, request perlu diperbaiki sesuai catatan.

Approval SLA diproses oleh scheduler aplikasi setiap lima menit.

---

## 15. Langkah 12 — Pantau Purchase Orders

Menu: **Procurement → Purchase Orders**

### Catatan tentang UI saat ini

Resource Purchase Orders saat ini berfungsi sebagai daftar/detail PO dan titik masuk goods receipt. Tidak ada tombol **New** pada resource ini. Karena itu, jangan mencari tombol create PO pada halaman tersebut.

Jika PO sudah tersedia:

1. Cari berdasarkan PO number, PR number, vendor, atau status.
2. Klik **View**.
3. Periksa vendor, total, status, dan Receipt status.
4. Gunakan **Print** jika membutuhkan dokumen.
5. Gunakan **Approve** hanya jika PO masih berada pada status yang dapat direvisi dan user mempunyai permission.
6. **Delete** hanya terlihat untuk PO Draft.

Goods receipt dan invoice hanya dapat diproses untuk PO berstatus Approved atau Issued.

---

## 16. Langkah 13 — Catat Goods Receipt

Menu: **Procurement → Purchase Orders → View → Goods receipts**

### 16.1 Record receipt

1. Buka detail PO approved/issued.
2. Buka relation **Goods receipts**.
3. Klik **Record receipt**.
4. Isi **Receipt date**.
5. Pilih **Receiver** yang assignment-nya sesuai scope PO.
6. Tambahkan **Received lines**.
7. Pilih PO line.
8. Isi quantity yang diterima.
9. Tambahkan **Delivery evidence** bila ada: Photo atau Surat jalan.
10. Isi document number, carrier, dan evidence notes bila diperlukan.
11. Isi receipt notes bila perlu.
12. Simpan.

Quantity receipt tidak boleh melebihi sisa quantity pada PO.

### 16.2 Koreksi receipt

Service backend mendukung koreksi receipt, tetapi tabel **Goods receipts** saat ini belum menampilkan action koreksi. Jangan mengubah database manual; gunakan prosedur support/internal yang disetujui sampai action koreksi tersedia di UI.

### Hasil yang benar

Receipt muncul pada relation Goods receipts dan Receipt status PO berubah sesuai total penerimaan.

---

## 17. Langkah 14 — Catat Invoice dan lakukan matching

Menu: **Procurement → Invoices**

Invoice hanya dapat dikaitkan ke PO Approved atau Issued yang berada dalam scope user finance.

### 17.1 Buat invoice

1. Buka **Invoices**.
2. Klik **New**.
3. Pilih **Approved purchase order**.
4. Isi **Invoice number**.
5. Isi **Invoice total**.
6. Isi **Due date**.
7. Tambahkan **Invoice lines**.
8. Untuk setiap line, pilih PO line, isi quantity, unit price, dan description bila diperlukan.
9. Upload minimal satu **Invoice evidence** berupa PDF, JPG, atau PNG.
10. Isi Notes bila perlu.
11. Simpan.

### 17.2 Hasil matching

Sistem membandingkan invoice dengan:

- nilai PO yang masih tersisa,
- jumlah yang sudah diterima,
- line PO,
- quantity, dan
- amount.

Invoice yang tidak match akan ditolak dan alasan mismatch harus diperbaiki sebelum dicatat ulang.

Invoice yang berhasil match akan dicatat dan di-approve oleh service matching. Lihat kolom **Match** dan **Review** pada tabel Invoices.

---

## 18. Langkah 15 — Catat Payment

Buka detail invoice melalui action **View**.

1. Buka relation **Payments**.
2. Klik **Record payment**.
3. Isi amount.
4. Isi payment date.
5. Isi reference number.
6. Upload **Payment proof** berupa PDF, JPG, atau PNG.
7. Isi notes bila perlu.
8. Simpan.

Payment hanya dapat dicatat untuk invoice yang sudah approved. Kolom invoice akan menunjukkan Paid, Partially paid, atau Unpaid sesuai total payment.

Untuk melihat bukti yang tersimpan, gunakan action **View proof**.

---

## 19. Langkah 16 — Buat Distribution

Menu: **Procurement → Distributions**

Distribution dibuat setelah stock diterima.

### 19.1 Buat distribution

1. Klik **New**.
2. Pilih **Umrah batch** aktif.
3. Isi **Distribution date**.
4. Pilih **Receipt mode**:
   - **Batch receipt** — penerimaan dicatat pada level batch.
   - **Individual pilgrim receipts** — penerimaan dicatat per jamaah.
5. Pilih status yang sesuai.
6. Tambahkan minimal satu item.
7. Isi quantity positif.
8. Simpan.

Sistem memeriksa ketersediaan stock dan tidak mengizinkan distribution melebihi stock yang diterima.

### 19.2 Receipt individual jamaah

Jika mode yang dipilih adalah **Individual pilgrim receipts**:

1. Buka detail distribution.
2. Buka relation **Pilgrim receipts**.
3. Klik **Record pilgrim receipt**.
4. Pilih distribution item.
5. Pilih pilgrim dari batch yang benar.
6. Isi quantity.
7. Pilih status awal.
8. Tambahkan receipt evidence bila diperlukan.
9. Simpan.

Action yang tersedia pada receipt individual:

- **Update receipt** — ubah quantity/status.
- **Confirm** — konfirmasi receipt.
- **Reject** — tolak receipt.
- **Attach evidence** — tambah photo atau surat jalan beserta metadata.

Jamaah harus berasal dari Umrah Batch yang sama dengan distribution.

### Hasil yang benar

Distribution menunjukkan item, quantity, batch/jamaah, status receipt, dan evidence yang tersimpan.

---

## 20. Langkah 17 — Sample Shipments antar-kantor

Menu: **Umrah Operations → Sample Shipments**

Alur status:

```text
Draft
  → Submitted
  → Procurement review
  → Approved
  → Shipped
  → Received
  → Confirmed
  → Stored / Returned
  → Complete
```

### 20.1 Buat shipment

1. Klik **New**.
2. Pilih **Origin purchase order** approved/issued dari office pengirim.
3. Periksa **Sender office**.
4. Pilih **Receiving office**.
5. Periksa **Responsible sender**.
6. Pilih receiver bila sudah diketahui.
7. Pilih cost center bila diperlukan.
8. Isi purpose.
9. Isi requested date dan planned ship date bila ada.
10. Isi tracking number bila ada.
11. Isi shipping cost bila ada.
12. Pilih approval route: Procurement only atau Procurement and finance.
13. Pilih condition.
14. Tambahkan minimal satu sample item, variant bila ada, quantity, dan condition.
15. Upload shipment evidence bila diperlukan.
16. Simpan.

### 20.2 Jalankan status shipment

Buka detail shipment dan gunakan action sesuai status:

1. **Submit** dari Draft.
2. **Procurement review** dari Submitted.
3. **Approve** dari Procurement review.
4. **Mark shipped** setelah barang dikirim.
5. **Confirm delivery** setelah office penerima menerima barang.
6. Pada form Confirm delivery, isi quantity, condition, disposition, received date, photo, dan signature.
7. **Mark returned** jika barang dikembalikan.
8. **Mark stored** jika barang disimpan.
9. **Complete** jika proses selesai.

Action hanya muncul pada status yang sesuai dan dapat dibatasi permission.

---

## 21. Langkah 18 — Audit log dan export

### 21.1 Lihat activity log

1. Buka **Settings → Activity Log**.
2. Gunakan search/filter untuk menemukan aktivitas.
3. Buka detail record jika perlu.

Melihat log membutuhkan `procurement.view`.

### 21.2 Export

Pada resource yang menyediakan export:

1. Filter data terlebih dahulu.
2. Pilih record bila export bersifat bulk.
3. Klik action **Export**.
4. Tunggu file selesai dibuat.
5. Download dari halaman/export notification yang tersedia.

Export membutuhkan `procurement.export`.

---

## 22. Urutan pekerjaan berdasarkan peran

| Peran | Menu utama | Pekerjaan utama |
| --- | --- | --- |
| Admin | Semua menu | Setup organisasi, role, assignment, master data, workflow, dan oversight. |
| Operasional | Requests, Umrah Operations, Purchase Orders | Membuat request, mengelola batch/jamaah, dan receiving sesuai scope. |
| Pengadaan | Requests, Procurement Reviews, Quotes, Master Data, Purchase Orders | Review request, quotation, rekomendasi vendor, dan receiving. |
| Manager | Approval Inbox | Approve, Reject, atau Return approval task. |
| Keuangan | Budgets, Invoices, Activity Log | Budget, invoice matching, payment, export, dan koreksi receipt sesuai permission. |
| Manajemen | Daftar yang diizinkan | Monitoring dan export. |
| Auditor | Activity Log dan daftar read-only | Pemeriksaan data dan audit. |
| Viewer | Daftar read-only | Melihat data sesuai office scope. |

Role aktual tetap mengikuti permission dan assignment user, bukan hanya nama role pada tabel.

---

## 23. Checklist satu transaksi dari awal sampai selesai

Gunakan checklist ini saat latihan pertama:

- [ ] Login Keycloak berhasil.
- [ ] Office aktif sudah benar.
- [ ] User memiliki assignment aktif.
- [ ] Office, branch, department, dan cost center tersedia.
- [ ] Budget aktif tersedia bila workflow memerlukannya.
- [ ] Role dan approver mapping tersedia.
- [ ] Umrah Batch tersedia.
- [ ] Departure Batch tersedia jika category mewajibkan.
- [ ] Pilgrim tersedia jika category/proses memerlukannya.
- [ ] Unit tersedia.
- [ ] Category aktif sudah dikonfigurasi.
- [ ] Custom field wajib sudah dibuat.
- [ ] Item dan variant aktif tersedia.
- [ ] Vendor aktif tersedia.
- [ ] Purchase Request tersimpan sebagai Draft.
- [ ] Purchase Request berhasil Submit.
- [ ] Procurement Review selesai.
- [ ] Quote dibuat untuk setiap vendor yang dibandingkan.
- [ ] Vendor direkomendasikan.
- [ ] Preview approval menampilkan approver yang benar.
- [ ] Request diserahkan ke approval.
- [ ] Semua approval selesai.
- [ ] Purchase Order tersedia.
- [ ] Goods Receipt dicatat.
- [ ] Invoice match dan approved.
- [ ] Payment dicatat beserta proof.
- [ ] Distribution dicatat.
- [ ] Receipt batch/jamaah dikonfirmasi.
- [ ] Activity log dapat ditelusuri.

---

## 24. Masalah umum saat mengikuti urutan

### Menu tidak terlihat

Periksa role, permission, office aktif, dan assignment. Menu Filament disembunyikan jika `canViewAny` atau policy tidak mengizinkan.

### Batch tidak muncul pada Pilgrims

Batch harus aktif dan statusnya tersedia untuk pendaftaran, biasanya Planned atau Open, serta berada pada office aktif.

### Unit atau variant tidak muncul pada Request

Pastikan item aktif, unit terhubung ke item, dan variant terhubung ke item yang sama.

### Tombol Submit tidak muncul

Requests hanya menampilkan Draft/Returned. Action Submit hanya muncul jika record correctable dan user memiliki permission submit.

### Quotation tidak bisa dibuat

Pastikan PR berstatus Submitted atau Procurement Review, vendor aktif, dan user memiliki scope update procurement.

### Handoff approval gagal

Buka **Preview approval**. Periksa workflow aktif, workflow version, stage, approver mapping, fallback, dan quotation/recommendation yang diwajibkan category.

### Approval Inbox kosong

Task hanya muncul setelah request berhasil di-handoff dan approver cocok dengan assignment scope. Periksa juga status task dan office aktif.

### PO tidak bisa dipakai untuk receipt/invoice

PO harus berstatus Approved atau Issued. Periksa Receipt status dan permission receiving/finance.

### Invoice tidak bisa dibuat

Gunakan approved purchase order, isi semua invoice lines, upload evidence, dan pastikan nilai invoice tidak melebihi PO atau total yang sudah diterima.

### Payment tidak bisa dicatat

Invoice harus sudah approved/matched. Pastikan amount, payment date, reference number, dan payment proof terisi.

### Distribution ditolak karena stock

Receipt belum cukup atau quantity distribution melebihi stock tersedia. Periksa Goods receipts pada PO.

### Confirm delivery shipment gagal

Isi quantity, condition, disposition, received date, photo, dan signature. Semua evidence tersebut wajib pada action confirm delivery.

### Attachment gagal di-upload

Pastikan storage private sudah dikonfigurasi dan folder storage dapat ditulis. Jangan mengubah attachment sensitif menjadi public.

---

## 25. Aturan aman saat menggunakan aplikasi

1. Selalu periksa office aktif sebelum create/edit.
2. Jangan memakai akun bersama untuk approval atau payment.
3. Isi alasan saat return, reject, koreksi, atau rekomendasi vendor.
4. Upload bukti pada field evidence yang benar.
5. Jangan menghapus record production untuk memperbaiki kesalahan; gunakan flow koreksi.
6. Jangan menjalankan `migrate:fresh` pada database production.
7. Jangan mengubah database secara manual untuk melewati policy aplikasi.
8. Pastikan audit log dan bukti attachment dapat ditelusuri.
9. Logout melalui action aplikasi agar session lokal dan Keycloak ikut diproses.
