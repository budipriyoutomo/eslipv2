# Decisions & Deviations

Catatan keputusan teknis dan perbedaan antara `Migration-Plan.md` dengan kode lama.
Source lama (sumber kebenaran): `d:/Project/htdocs/eslip/application` (CodeIgniter 3.1.3).

Format: **ID — judul** · status (`accepted`, `open`, `superseded`) · fase terkait.

---

## D-001 — Lokasi source lama · accepted · semua fase
Plan menyebut `application/`, tetapi repo ini (`eslipv2`) tidak berisi kode lama.
Kode lama ada di `d:/Project/htdocs/eslip/application`.
Proyek Laravel 10 di `d:/Project/eslip` (format Songfa, Google Drive, modul pelamar) **bukan bagian dari migrasi ini**.

## D-002 — Template ND & LLAO tidak ada di source · open · Fase 4
Source hanya memiliki `Tmsteslip.php` (Standard) dan `Tmsteslipxft.php` (XFT), beserta model/view-nya.
`Tmsteslipnd.php` / `Tmsteslipllao.php` tidak ditemukan. Mapping ND/LLAO di plan belum terverifikasi.
Butuh: source production atau sample Excel ND/LLAO dari user.

## D-003 — Kredensial SMTP · accepted · Fase 1/7
Plan menyebut `config/email_secret.php` dengan 3 akun. Faktanya file itu tidak ada; kredensial SMTP
di-hardcode plaintext di `Tmsteslip.php`, `Tmsteslipxft.php`, `Send.php`, dan hanya **satu akun** yang dipakai
(host `ssl://...:465`, akun HRD Boga Jabar).
Keputusan: mailer `smtp_bogajabar`, `smtp_maharasa`, `smtp_gmail` didefinisikan di `config/mail.php`,
semua kredensial dari `.env`. **Rekomendasi: rotasi password SMTP lama** karena tersimpan plaintext di source.

## D-004 — Template dipilih per menu, bukan per PT · accepted (disetujui user) · Fase 2/5
Di sistem lama, template ditentukan oleh menu (controller) yang dipilih admin, bukan oleh PT.
Tabel `tmstbrand` tidak punya kolom template. Plan menaruh `template_type` di `companies`.
Keputusan: `companies.template_type` sebagai *default*, dan `payslip_batches.template_type` disimpan per batch
(bisa di-override saat upload). Template untuk render/import selalu dibaca dari **batch**, bukan company.

## D-005 — Mapping kolom Excel (terverifikasi) · accepted · Fase 4
Kolom 1-based, loop mulai baris 1, berhenti bila kolom 1 (NIK) kosong.

| Field | Standard | XFT |
|---|---|---|
| nik / nama / jabatan | 1 / 2 / 3 | 1 / 2 / 3 |
| periode (diabaikan, dari form) | 4 | 4 |
| gaji | 5 | 5 |
| bonus | – | 6 |
| svctetap / svcvariabel / target | 6 / 7 / 8 | – |
| lembur | 9 | 7 |
| uangmakan | 10 | 8 |
| transport | 11 | 9 |
| pengmcu | 12 | 10 |
| bpjsnakerjht | 13 | 11 |
| bpjsnakerjp | 14 | 12 |
| bpjssehat | 15 | 13 |
| potmcu | 16 | 14 |
| pinalty | 17 | – |
| potlain | 18 | 15 |
| ketpot | 19 | 16 |
| penlain | 20 | 17 |
| ketpen | 21 | 18 |
| sakit | 22 | 19 |
| alpha | 23 | 20 |
| cutdak | 24 | 21 |
| hadir | 25 | 22 |
| thp | 26 | 23 |
| penerbit / namapt (diabaikan, dari form) | 27 / 28 | 24 / 25 |
| email | 29 | 26 |

## D-006 — Field yang belum ada di skema plan · accepted · Fase 2
`ketpen` (keterangan penerimaan) dan data kehadiran (`sakit`, `alpha`, `cutdak`, `hadir`) tidak ada di skema
`payslips` plan. Ditambahkan: `earning_note` (text nullable) dan `attendance` (json: `sick`, `absent`,
`sudden_leave`, `present`).

## D-007 — Total penerimaan & THP · accepted · Fase 4
- Total penerimaan dihitung di PDF lama (bukan dari Excel):
  - Standard: `gaji+svctetap+svcvariabel+target+lembur+uangmakan+transport+pengmcu+penlain`
  - XFT: `gaji+bonus+lembur+uangmakan+transport+pengmcu+penlain`
- Interface `PayslipTemplate` ditambah `totalEarnings()`.
- `thp` **diambil dari Excel** (Standard kol. 26, XFT kol. 23), tidak dihitung. Sesuai plan: tampilkan warning
  (tidak memblokir) bila `thp != totalEarnings - totalDeductions`.

## D-008 — Baris header Excel · open · Fase 5
Loop lama mulai dari baris 1 tanpa skip header, sehingga file lama diasumsikan **tanpa baris header**.
Bila sample asli punya header, import baru harus skip baris yang kolom NIK-nya bukan data. Konfirmasi via sample.

## D-009 — Perilaku lama yang sengaja diubah · accepted · Fase 6/7
| Lama | Baru | Alasan |
|---|---|---|
| Body email `Dear {email}, ...` | `Dear {nama}, ...` | Bug: menyapa dengan alamat email |
| Periode `date("F-Y")` → `January-2025` | Nama bulan Indonesia (`Januari 2025`) | Konsisten dengan label Bahasa Indonesia; **konfirmasi saat paralel run** |
| Tanggal terbit dicetak mentah `Y-m-d` | Format `d F Y` Indonesia | Keterbacaan |
| Kota "Bandung" hardcode | `companies.city` | Plan poin 6 |
| Logo hardcode berdasarkan `id` brand 1–4 (`logo-ST/PL/BJ/XF.png`) | `companies.logo_path` | Kolom `tmstbrand.logo` ada tapi tak dipakai |
| XFT tidak menulis log `tstatus`; gagal kirim → `show_error()` menghentikan loop | Semua template menulis `delivery_logs` | Plan poin 2 |
| `tmstbrand.tipe` (2 cabang header identik) | Tidak dimigrasi | Tidak ada perbedaan perilaku |

## D-010 — Posisi garis PDF berbeda per template · accepted · Fase 6
Standard: garis total di y=115/121. XFT: y=105/110 (karena baris lebih sedikit). Port layout harus
menghitung posisi dari jumlah baris, bukan hardcode.

## D-011 — Urutan label tidak bergantung pada JSON · accepted · Fase 2/4
Tipe `JSON` MySQL menormalisasi (mengurutkan ulang) key objek. Urutan label earnings/deductions di PDF/UI
selalu diambil dari Template, bukan dari urutan key `payslips.earnings` / `payslips.deductions`.

## D-012 — Proteksi password PDF · open · Fase 6
`setasign/fpdi-protection` hanya mendukung enkripsi RC4 (lemah). Kandidat: tetap FPDF + fpdi-protection
(layout 1:1 paling mudah) vs mPDF/TCPDF (AES-128/256). Keputusan final di Fase 6.

## D-013 — Dump DB lama · open · Fase 9
`localhost.sql` di source lama berisi tabel aplikasi lain (training). Dibutuhkan dump production berisi
`user`, `tmstbrand`, `tstatus`, `tmsteslip`, `tmsteslipxft` untuk `eslip:import-legacy`.
Kolom `tmstbrand` yang terlihat di kode: `id, namaPT, alamat, kota, telp, logo, tipe`.
Kolom `user`: `id, username, password(varchar 50, sha1), role enum('Admin','User','SuperAdmin'), nama, ...`.

## D-014 — Setup project · accepted · Fase 1
- Laravel 13 dipasang langsung di root `eslipv2` (bukan subfolder `eslip-v2`).
- Laravel Boost (disarankan oleh `CLAUDE.md`/`AGENTS.md` bawaan skeleton) tidak dipasang; tidak diminta plan.
- Ekstensi `intl` belum aktif di PHP lokal; wajib ada di server (lihat checklist Fase 9). Kode tidak boleh
  bergantung pada `intl` di test lokal sampai ekstensi diaktifkan.
- Test lokal & CI memakai SQLite in-memory; production MySQL/MariaDB.
- Timezone default `Asia/Jakarta`, locale `id`.

## D-015 — Kolom enum disimpan sebagai string · accepted · Fase 2
`role`, `template_type`, `status`, `delivery_status` memakai kolom `string` + enum cast PHP, bukan tipe `ENUM`
MySQL. Alasan: menambah case (mis. template baru) tidak butuh `ALTER TABLE`, dan portabel ke SQLite untuk test.
Validasi nilai dijaga oleh enum cast.

## D-016 — `delivery_logs` untuk histori lama · accepted · Fase 2/9
Tabel `tstatus` lama tidak punya relasi ke payslip baru. Maka `delivery_logs.payslip_id` **nullable**, ditambah
kolom `recipient` (alamat tujuan) dan `context` (json: snapshot nik/nama/periode/namapt dari `tstatus`).
Log baru (Fase 7) selalu mengisi `payslip_id`.

## D-017 — Tanpa cast `hashed` pada `users.password` · accepted · Fase 2/3
Cast `hashed` bawaan akan meng-hash ulang string sha1 lama saat import, sehingga login legacy rusak.
Password di-hash eksplisit dengan `Hash::make()` di kode (form user, rehash Fase 3). Kolom `email_verified_at`
dihapus (tidak dipakai; login dengan `username`).

## D-018 — Kolom tambahan di `payslip_batches` · accepted · Fase 2
- `template_type` (D-004).
- Index `(company_id, period)` untuk dashboard.
- FK `company_id` → `restrictOnDelete` (company dengan batch tidak bisa dihapus);
  `uploaded_by` → `nullOnDelete` (histori batch tetap ada walau user dihapus);
  `payslips` & `delivery_logs` → `cascadeOnDelete`.

## D-019 — Login legacy sha1 via custom user provider · accepted · Fase 3
`App\Auth\LegacyAwareUserProvider` (driver `legacy-eloquent` di `config/auth.php`) meng-override
`validateCredentials()` dan `rehashPasswordIfRequired()`. User `legacy_sha1 = true` diverifikasi oleh
`App\Support\LegacyPasswordHasher`, lalu password langsung di-rehash dengan `Hash::make` dan flag di-clear —
semua dalam alur standar `Auth::attempt()`. Login lama menjalankan rule `trim` sebelum sha1, jadi input mentah
dan input ter-trim sama-sama dicoba.

## D-020 — Akses manajemen user dipersempit · accepted · Fase 3
Lama: CRUD user terbuka untuk **semua role** (`$access = SuperAdmin, Admin, User`); hanya delete yang menolak
role `User`. Artinya role `User` bisa membuat SuperAdmin (privilege escalation).
Baru (sesuai plan, middleware `role:super_admin,admin`):
- Role `user` tidak bisa mengakses `/users` sama sekali.
- Admin hanya bisa memberi role `admin`/`user`, dan tidak bisa edit/hapus SuperAdmin.
- Tidak ada yang bisa menghapus akunnya sendiri.
Aturan di `App\Policies\UserPolicy`.

## D-021 — Password plaintext di tabel `user` lama · open · Fase 9
`User::create_action/update_action` lama menyimpan password **mentah** dari form (tanpa sha1), sementara login
membandingkan `sha1(input)`. Akibatnya tabel `user` lama kemungkinan berisi campuran hash sha1 dan plaintext.
Rencana `eslip:import-legacy`: nilai yang cocok pola sha1 (40 hex) → salin + `legacy_sha1 = true`;
selain itu dianggap plaintext → langsung `Hash::make()` + `legacy_sha1 = false`. Verifikasi dengan dump production.
Selain itu, datatables user lama mengekspos kolom `password` ke browser.

## D-022 — Rate limit login · accepted · Fase 3
5 percobaan gagal per kombinasi `username|IP` per 60 detik (gaya Breeze: hanya kegagalan yang dihitung, reset
saat sukses). Diimplementasikan di `App\Http\Requests\Auth\LoginRequest`.

## D-023 — UI auth custom minimal · accepted · Fase 3
Laravel Breeze tidak dipakai (sudah digantikan starter kit sejak Laravel 12 dan membawa registrasi/reset email
yang tidak dibutuhkan). Dipakai Blade + Tailwind v4 (Vite) minimal: login, logout, CRUD user.
Tidak ada registrasi publik dan tidak ada reset password via email (admin yang reset).

## D-024 — Nama field English di JSON earnings/deductions · accepted · Fase 4
Sesuai konvensi plan bagian 7, nama lama hanya muncul di Template (komentar `columnMap()`) dan import legacy.

| Lama | Baru | Grup |
|---|---|---|
| nik / nama / jabatan / email | `nik` / `name` / `position` / `email` | identitas |
| gaji | `base_salary` | earning |
| bonus (XFT) | `bonus` | earning |
| svctetap / svcvariabel / target (Standard) | `fixed_service` / `variable_service` / `target` | earning |
| lembur / uangmakan / transport | `overtime` / `meal_allowance` / `transport_allowance` | earning |
| pengmcu / penlain | `mcu_refund` / `other_earnings` | earning |
| bpjsnakerjht / bpjsnakerjp / bpjssehat | `bpjs_jht` / `bpjs_jp` / `bpjs_health` | deduction |
| potmcu / pinalty (Standard) / potlain | `mcu_deduction` / `safety_shoes_penalty` / `other_deductions` | deduction |
| ketpen / ketpot | `earning_note` / `deduction_note` | catatan |
| sakit / alpha / cutdak / hadir | `sick` / `absent` / `sudden_leave` / `present` | `attendance` |
| thp | `take_home_pay` | kolom |

Label PDF di `lang/id/payslip.php`. Urutan label = urutan `earningFields()` / `deductionFields()` (D-011).

## D-025 — Template ND & LLAO belum diimplementasi · accepted (sementara) · Fase 4
Disetujui user: kerjakan Standard + XFT dulu. Enum `PayslipTemplateType::Nd/Llao` tetap ada, tetapi
`TemplateResolver::resolve()` melempar `UnsupportedTemplateException` dan `supported()` hanya mengembalikan
Standard + XFT (dipakai form upload Fase 5). Tambah template = 1 class + 1 baris di `TemplateResolver::TEMPLATES`.

## D-026 — Nominal kosong dianggap 0 · accepted · Fase 4
Import lama tidak memvalidasi (`_rules()` tidak pernah dipanggil di `proses()`), dan sel kosong tersimpan sebagai 0.
Baru: field nominal `nullable|numeric` (kosong → `0.00` via `App\Support\Money`), kecuali `take_home_pay` wajib.
Teks di kolom nominal ditolak. `nik`, `name`, `email` wajib. Aritmetika memakai `brick/math` (scale 2, HALF_UP);
float dari Excel dinormalisasi lewat `sprintf('%.6F')` sebelum dibulatkan.
