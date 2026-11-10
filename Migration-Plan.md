# Eslip — Migration Plan: CodeIgniter 3 → Laravel 13 (PHP 8.4)

> Dokumen ini adalah instruksi kerja untuk coding agent. Baca seluruh dokumen sebelum mulai.
> Penjelasan dalam Bahasa Indonesia, sedangkan code, nama class, dan konvensi dalam English.

---

## 0. Aturan untuk Agent

- Kerjakan **per fase secara berurutan**. Jangan lompat fase.
- Setiap fase harus selesai dengan **test hijau** (`php artisan test`) sebelum lanjut.
- **Source lama adalah sumber kebenaran.** Jika dokumen ini berbeda dengan kode di `application/`, ikuti kode lama lalu catat perbedaannya di `docs/DECISIONS.md`.
- Jangan mengubah skema DB production secara destruktif. Gunakan migration baru dan jangan drop tabel lama sampai cutover.
- Jangan commit kredensial. Semua secret masuk `.env`, dan `.env.example` hanya berisi placeholder.
- Gunakan `declare(strict_types=1);`, typed properties, `readonly` jika cocok, dan `final` class secara default.
- Tools: Pest untuk test, Laravel Pint untuk formatting, Larastan level 6+ untuk static analysis.
- Setelah tiap fase, update checklist di bagian **9. Progress** di file ini.

---

## 1. Ringkasan Aplikasi Lama

Aplikasi e-slip gaji dengan alur berikut:

1. Admin login.
2. Admin memilih PT/brand, mengisi periode gaji dan tanggal terbit, lalu upload file `.xls`.
3. Data Excel dimasukkan ke tabel staging (`tmsteslip*`).
4. Admin klik proses. Untuk setiap baris: generate PDF slip (FPDF), kirim email beserta lampiran PDF, simpan log ke tabel status (`Tstatus_model`), hapus file PDF, lalu hapus baris staging.

### Stack lama
- CodeIgniter **3.1.3**, PHP 5.x/7.x (`composer.json`: `php >=5.2.4`)
- `libraries/Excel_reader` (spreadsheet-excel-reader, hanya `.xls`, **rusak di PHP 8**)
- `libraries/pdf` (FPDF)
- `libraries/datatables` (server-side DataTables)
- CI Email library, dengan multi akun SMTP di `application/config/email_secret.php` (`smtp_maharasa`, `smtp_bogajabar`, `smtp_gmail`)
- Auth custom: `Auth_model` dengan `sha1($password)`, role di session (`SuperAdmin`, `Admin`, `User`)
- `core/MY_Controller.php` berisi `$access` (`*`, `@`, atau array role)

### File penting yang wajib dibaca agent
| File | Isi |
|---|---|
| `application/controllers/Tmsteslip.php` | Template standar (ada svctetap, svcvariabel, target, pinalty) |
| `application/controllers/Tmsteslipnd.php` | Template ND |
| `application/controllers/Tmsteslipllao.php` | Template LLAO |
| `application/controllers/Tmsteslipxft.php` | Template XFT (ada bonus) |
| `application/models/Tmsteslip*_model.php` | Tabel staging per template |
| `application/models/Tstatus_model.php` | Log pengiriman |
| `application/models/Master.php` | Data brand/PT (`getallbrand`, `getbrand`) |
| `application/models/Auth_model.php` | Login sha1 |
| `application/models/User_model.php`, `controllers/User.php` | CRUD user |
| `application/core/MY_Controller.php` | Role check |
| `application/views/tmsteslip*/` | UI upload dan list |

### File yang TIDAK perlu dimigrasi
- `controllers/Send.php`: test controller dengan email hardcoded.
- `controllers/Eslippdf.php`: PDF dummy.
- `system/`, `tests/` bawaan CI.

---

## 2. Masalah di Kode Lama (harus diperbaiki, bukan disalin)

1. **4 controller hampir identik.** Bedanya hanya mapping kolom Excel dan komponen gaji. Solusi: satu flow + Strategy pattern per template.
2. **Kirim email sinkron dalam loop HTTP request.** Rawan timeout dan gagal di tengah. Solusi: queue job per karyawan.
3. **Password `sha1` tanpa salt.** Solusi: rehash transparan ke bcrypt/argon2 saat login.
4. **`error_reporting(E_ALL ^ E_NOTICE)`** dipakai untuk menyembunyikan error parsing. Solusi: validasi baris Excel secara eksplisit dan laporkan baris yang invalid.
5. **Periode diparse dengan `strtotime(substr(...))`.** Solusi: validasi `date_format` + Carbon.
6. **Teks hardcoded di PDF** ("Bandung", header PT, alamat). Solusi: simpan di tabel `companies`.
7. **PDF slip gaji tidak dilindungi.** Solusi: PDF ber-password (default: NIK, bisa dikonfigurasi per company).
8. **Kredensial SMTP di file PHP.** Solusi: `.env` + mailer per company.
9. **`unlink()` gagal menyebabkan `break` loop.** Solusi: gunakan `Storage` disk privat dan cleanup terjadwal.

---

## 3. Target Stack

- PHP **8.4**, Laravel **13**
- DB: PostgreSQL (diubah dari MySQL/MariaDB atas permintaan user, lihat D-044). DB lama tetap MySQL sebagai sumber import.
- Excel: `phpoffice/phpspreadsheet`, agar file `.xls` lama dan `.xlsx` sama-sama didukung. (`openspout` lebih ringan, tetapi tidak membaca `.xls`.)
- PDF: `setasign/fpdf` + `setasign/fpdi` (port layout lama 1:1) dengan proteksi password via `setasign/fpdi-protection` atau library sejenis yang kompatibel PHP 8.4. Catat pilihan final di `DECISIONS.md`.
- Queue: driver `database` (cukup untuk skala ini). Siapkan Supervisor config contoh.
- UI: Blade + Livewire 3 (atau Blade + Alpine). Tabel menggunakan Livewire atau `yajra/laravel-datatables`.
- Auth: Laravel Breeze (Blade) atau custom minimal. Role sebagai enum kolom `users.role`.
- Test: Pest + Laravel Pint + Larastan.

---

## 4. Arsitektur

Pendekatan **modular sederhana** (tanpa full DDD/CQRS, karena app kecil):

```
app/
├── Actions/
│   └── Payslip/
│       ├── ImportPayslipBatch.php      # parse Excel → PayslipBatch + Payslip rows
│       ├── DispatchPayslipBatch.php    # dispatch SendPayslipJob per row
│       └── GeneratePayslipPdf.php      # render PDF untuk 1 payslip
├── Enums/
│   ├── UserRole.php                    # SuperAdmin, Admin, User
│   ├── PayslipTemplateType.php         # Standard, Nd, Llao, Xft
│   └── DeliveryStatus.php              # Pending, Sent, Failed
├── Http/
│   ├── Controllers/
│   │   ├── PayslipBatchController.php
│   │   └── UserController.php
│   ├── Middleware/EnsureUserHasRole.php
│   └── Requests/StorePayslipBatchRequest.php
├── Jobs/SendPayslipJob.php
├── Mail/PayslipMail.php
├── Models/
│   ├── Company.php
│   ├── PayslipBatch.php
│   ├── Payslip.php
│   ├── DeliveryLog.php
│   └── User.php
├── Payslip/
│   ├── Templates/
│   │   ├── PayslipTemplate.php         # interface
│   │   ├── StandardTemplate.php
│   │   ├── NdTemplate.php
│   │   ├── LlaoTemplate.php
│   │   └── XftTemplate.php
│   ├── TemplateResolver.php            # PayslipTemplateType → PayslipTemplate
│   └── Data/PayslipLine.php            # DTO: label, amount
└── Support/LegacyPasswordHasher.php
```

### Interface `PayslipTemplate`

```php
interface PayslipTemplate
{
    /** @return array<int, string> column index (1-based, sesuai Excel lama) => field name */
    public function columnMap(): array;

    /** @return array<string, string> validation rules per field */
    public function rules(): array;

    /** @return list<PayslipLine> */
    public function earnings(Payslip $payslip): array;

    /** @return list<PayslipLine> */
    public function deductions(Payslip $payslip): array;

    public function totalDeductions(Payslip $payslip): string; // decimal string, gunakan bcmath/brick/math
}
```

> Simpan nominal sebagai `decimal(15,2)`. Hindari float dalam perhitungan dan gunakan `brick/math` atau bcmath.

### Mapping kolom (dari kode lama, **agent wajib verifikasi ulang** di controller lama karena sebagian terpotong)

Kolom 1–3 sama di semua template: `1 nik`, `2 nama`, `3 jabatan`. Kolom 4 (periode) **diabaikan**, karena periode diambil dari input form.

| Field | Standard | ND | LLAO | XFT |
|---|---|---|---|---|
| gaji | 5 | 5 | 5 | 5 |
| bonus | – | – | – | 6 |
| svctetap | 6 | – | – | – |
| svcvariabel | 7 | – | – | – |
| target | 8 | – | – | – |
| lembur | 9 | 6 | 6 | 7 |
| uangmakan | 10 | 7 | 7 | 8 |
| transport | 11 | 8 | 8 | 9 |
| pengmcu | 12 | 9 | 9 | 10 |
| bpjsnakerjht | 13 | 10 | 10 | 11 |
| bpjsnakerjp | 14 | 11 | 11 | 12 |
| bpjssehat | 15 | 12 | 12 | 13 |
| potmcu | 16 | 13 | 13 | 14 |
| pinalty | 17 | – | – | – |
| potlain | 18 | 14 | 14 | 15 |
| ketpot | 19 | 15 | 15 | 16 |
| penlain | ? | 16 | 16 | 17 |
| thp, email, … | ? | ? | ? | ? |

**TODO agent:** lengkapi baris `?` dari controller lama (bagian loop `for ($i = 1; ...)`).

### Rumus total potongan (dari kode lama)
- Standard: `bpjsnakerjht + bpjsnakerjp + bpjssehat + potmcu + pinalty + potlain`
- ND / LLAO / XFT: `bpjsnakerjht + bpjsnakerjp + bpjssehat + potmcu + potlain`
- `thp` diambil dari data (bukan dihitung). **Verifikasi** apakah `thp` berasal dari Excel atau kolom terhitung di model lama. Jika dari Excel, tambahkan validasi bahwa `thp == total penerimaan − total potongan` dan beri warning jika tidak sama (jangan blokir).

---

## 5. Skema Database Baru

```
companies
  id, code (unique), name, address, city, phone, logo_path,
  template_type (enum), mailer (string, nama mailer di config/mail.php),
  from_address, from_name, pdf_password_field (default 'nik'),
  timestamps

users
  id, username (unique), name, email (nullable), password,
  role (enum: super_admin, admin, user), legacy_sha1 (bool, default false),
  timestamps

payslip_batches
  id, company_id (fk), period (date, tanggal 1 bulan), issued_at (date),
  source_filename, uploaded_by (fk users), status (enum: draft, processing, done),
  total_rows, sent_count, failed_count, timestamps

payslips
  id, payslip_batch_id (fk), nik, name, position, email,
  earnings (json), deductions (json), deduction_note (text nullable),
  take_home_pay (decimal 15,2), raw_row (json),
  delivery_status (enum: pending, sent, failed), sent_at (nullable),
  timestamps
  index(payslip_batch_id, delivery_status)

delivery_logs
  id, payslip_id (fk), status, error_message (text nullable),
  attempted_at, timestamps
```

> `earnings`/`deductions` disimpan sebagai JSON `{field: "amount"}` agar 4 template tidak membutuhkan 4 tabel. `raw_row` berguna untuk audit dan debugging.

### Migrasi data lama
- Buat command `php artisan eslip:import-legacy` yang:
  - Copy `user` lama ke `users` dengan `legacy_sha1 = true` (password hash sha1 disalin apa adanya).
  - Copy data brand dari tabel master lama ke `companies` (map ke `template_type`).
  - Copy tabel status lama ke `delivery_logs` sebagai histori (opsional, read-only).
- Command harus **idempotent** (aman dijalankan berulang).

---

## 6. Fase Pengerjaan

### Fase 1 — Setup project
- [x] `laravel new eslip-v2` (PHP 8.4, Laravel 13), Pest, Pint, Larastan. *(dipasang di root `eslipv2`, lihat D-014)*
- [x] `.env.example` lengkap (DB, mailers per company, queue `database`).
- [x] CI sederhana (GitHub Actions): pint `--test`, larastan, pest.
- [x] Buat `docs/DECISIONS.md`.

**Done when:** `php artisan test` hijau dan pipeline CI hijau.

### Fase 2 — Schema, Models, Enums
- [x] Migrations sesuai bagian 5. *(+ `template_type` di batch, `earning_note`/`attendance` di payslip, lihat D-004, D-006, D-016)*
- [x] Models + casts (enum, json, decimal, date) + factories.
- [x] Enums `UserRole`, `PayslipTemplateType`, `DeliveryStatus`. *(+ `PayslipBatchStatus`)*

**Done when:** test factory dan relasi lulus.

### Fase 3 — Auth + Role + User management
- [x] Login dengan `username` + password.
- [x] `LegacyPasswordHasher`: jika `legacy_sha1 = true` dan `hash_equals(sha1($input), $stored)`, maka login sukses, password di-rehash dengan `Hash::make`, dan set `legacy_sha1 = false`. *(via custom user provider, D-019)*
- [x] Middleware `role:super_admin,admin`.
- [x] CRUD user (hanya SuperAdmin/Admin boleh delete, mengikuti logika `User::delete` lama). *(akses dipersempit, D-020)*
- [x] Rate limit login.

**Tests:** login normal, login legacy sha1 dan terbukti rehash, password salah, role denied.

### Fase 4 — Templates (Strategy)
- [x] Interface `PayslipTemplate` + 4 implementasi + `TemplateResolver`. *(Standard + XFT saja; ND/LLAO menunggu source/sample, D-025)*
- [x] Lengkapi mapping kolom (bagian 4, isi semua `?`). *(lihat D-005, nama field baru D-024)*

**Tests:** unit test per template (mapping, total potongan, urutan label earnings/deductions sesuai PDF lama).

### Fase 5 — Import Excel
- [x] `StorePayslipBatchRequest`: `company_id`, `period` (`date_format:Y-m`), `issued_at`, `file` (`mimes:xls,xlsx`, max 10MB). *(+ `template_type` opsional; `extensions` bukan `mimes`, D-028)*
- [x] `ImportPayslipBatch`: baca sheet pertama, berhenti di baris dengan kolom NIK kosong (sama seperti lama), validasi tiap baris, dan kumpulkan error per nomor baris.
- [x] Jika ada error, **tidak ada data yang disimpan** (DB transaction) dan tampilkan daftar error.
- [x] Halaman **preview** batch sebelum dikirim.

**Tests:** gunakan fixture `.xls` di `tests/Fixtures/` (minta sample asli yang sudah dianonimkan dari user, satu per template).

### Fase 6 — Generate PDF
- [x] `GeneratePayslipPdf` dengan port layout FPDF lama (ukuran A5 landscape, header company, NIK/nama/jabatan/periode, 2 kolom PENERIMAAN/POTONGAN, TOTAL, TAKE HOME PAY, tempat dan tanggal terbit).
- [x] Format angka `number_format($x, 2, ',', '.')` (sama dengan lama).
- [x] Password PDF berdasarkan `companies.pdf_password_field`. *(TCPDF AES-256, default NIK, D-032)*
- [x] Simpan sementara di disk `local` (private), bukan `public/`.
- [x] Tombol "Preview PDF" di halaman batch (stream langsung, tanpa simpan).

**Tests:** PDF valid dan bisa di-parse, teks kunci muncul, serta total sesuai.

### Fase 7 — Queue Email + Delivery Log
- [x] `PayslipMail` (Mailable) dengan subject `E-Slip {company}` dan body `Dear {nama}, Berikut E-Slip Gaji Bulan {periode}` (mengikuti lama, pindahkan ke Blade view).
- [x] Mailer dipilih dari `companies.mailer`.
- [x] `SendPayslipJob`: generate PDF, kirim, tulis `delivery_logs`, update `payslips.delivery_status`, lalu hapus file PDF di blok `finally`.
- [x] `tries = 3`, `backoff = [60, 300]`, `failed()` mencatat log. *(`maxExceptions = 3`, D-036)*
- [x] Throttle pengiriman per mailer (`RateLimited` middleware) agar tidak kena limit SMTP.
- [x] `DispatchPayslipBatch` dengan `Bus::batch()` dan progress di UI.
- [x] Fitur **kirim ulang yang gagal** per batch.
- [x] Contoh config Supervisor di `docs/deploy/supervisor.conf`.

**Tests:** `Mail::fake()`, `Queue::fake()`, sukses, gagal lalu status failed, serta retry.

### Fase 8 — UI
- [x] Dashboard: daftar batch (company, periode, total, terkirim, gagal).
- [x] Form upload batch.
- [x] Detail batch: tabel payslip + status + preview PDF + resend.
- [x] Riwayat pengiriman (dari `delivery_logs`).
- [x] *(tambahan)* CRUD PT (D-030, D-039) dan hapus batch draft (D-040).

### Fase 9 — Migrasi Data & Cutover
- [x] `eslip:import-legacy` (bagian 5). *(diuji dengan DB legacy tiruan; belum ke data asli, D-041)*
- [ ] **Paralel run**: 1 periode gaji diproses di sistem lama dan baru (email baru dikirim ke alamat test), lalu bandingkan PDF. *(prosedur di `docs/deploy/CHECKLIST.md` §5; pakai `ESLIP_MAIL_OVERRIDE_TO`)*
- [x] Checklist deploy: PHP 8.4 extensions (`gd`, `zip`, `xml`, `mbstring`, `bcmath`, `intl`), queue worker, cron `schedule:run`, storage permission.
- [x] Cleanup terjadwal: hapus file temp > 1 hari.

---

## 7. Konvensi Kode

- Controller tipis: validasi via FormRequest, logic di Action.
- Action: satu public method `handle()` / `__invoke()`.
- Tidak ada query di Blade.
- Nama tabel/kolom: `snake_case` English (`take_home_pay`, bukan `thp`). Mapping dari nama lama hanya ada di Template dan command import legacy.
- Label di PDF/UI tetap Bahasa Indonesia (sesuai slip lama: `GAJI`, `LEMBUR`, `UANG MAKAN`, `BPJS KETENAGAKERJAAN`, `TOTAL POTONGAN`, `TAKE HOME PAY`, dll). Simpan di `lang/id/payslip.php`.
- Commit kecil per sub-task dengan conventional commits (`feat:`, `fix:`, `test:`, `refactor:`).

---

## 8. Pertanyaan Terbuka (tanyakan ke user sebelum fase terkait)

1. Sample file Excel asli per template (dianonimkan), dibutuhkan untuk **Fase 4–5**.
2. Password PDF: pakai NIK, tanggal lahir, atau tanpa password? Dibutuhkan untuk **Fase 6**.
3. Apakah `thp` di Excel selalu benar, atau perlu dihitung sistem? Dibutuhkan untuk **Fase 4**.
4. Daftar PT/brand aktif beserta template dan akun SMTP masing-masing. Dibutuhkan untuk **Fase 5/7**.
5. Hosting target (shared hosting atau VPS)? Ini menentukan apakah queue worker via Supervisor memungkinkan. Dibutuhkan untuk **Fase 7/9**.
6. Perlu kanal tambahan (misal WhatsApp) di masa depan? Jika ya, desain `SendPayslipJob` dengan channel interface.

---

## 9. Progress

| Fase | Status | Catatan |
|---|---|---|
| 1. Setup | ✅ | Laravel 13.40, PHP 8.4.22, Pest 5.3, Larastan 3.14 (level 6), Pint. CI di `.github/workflows/ci.yml` (belum dijalankan; repo belum punya remote). Lihat `docs/DECISIONS.md` D-001…D-014. |
| 2. Schema & Models | ✅ | 25 test hijau (SQLite). Migrations belum diuji di MySQL/MariaDB. D-015…D-018. |
| 3. Auth & Role | ✅ | 62 test hijau. Smoke test `php artisan serve` (login → dashboard → /users) OK. D-019…D-023. |
| 4. Templates | 🟨 | Standard + XFT selesai, 114 test hijau. ND + LLAO tertunda (D-002, D-025). D-024…D-026. |
| 5. Import Excel | 🟨 | Selesai untuk Standard + XFT, 143 test hijau. Fixture masih sintetis; uji ulang dengan sample asli (D-027). D-027…D-031. |
| 6. PDF | ✅ | 164 test hijau. Enkripsi diverifikasi dengan `pdftotext`. Library & password = default (D-032), konfirmasi user. D-032…D-034. |
| 7. Queue Email | ✅ | 184 test hijau. E2E nyata: worker `database` + mailer `log`, 3/3 terkirim, file temp terhapus. Hosting & SMTP per PT masih asumsi (D-035). D-035…D-038. |
| 8. UI | ✅ | 216 test hijau. Smoke test semua halaman di server lokal: 200. D-039, D-040. |
| 9. Migrasi & Cutover | 🟨 | Kode & dokumen siap (227 test hijau): import legacy, cleanup terjadwal, `docs/deploy/CHECKLIST.md`. Menunggu dump DB, server, sample Excel & SMTP asli untuk import nyata, paralel run, cutover (D-043). |