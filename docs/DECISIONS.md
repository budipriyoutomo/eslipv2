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

## D-012 — Proteksi password PDF · superseded oleh D-032 · Fase 6
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
- Test lokal & CI memakai SQLite in-memory; production PostgreSQL (D-044, sebelumnya MySQL/MariaDB).
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

## D-021 — Password plaintext di tabel `user` lama · accepted (ditangani di D-041) · Fase 9
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

## D-027 — Header row & fixture sintetis · accepted (sementara) · Fase 5
- Sample Excel asli belum tersedia. Fixture test dibuat on-the-fly (`tests/Support/PayslipSpreadsheet.php`)
  dari mapping terverifikasi (D-005), dalam format `.xls` dan `.xlsx`. **Wajib diuji ulang dengan sample asli**
  (anonim) sebelum paralel run Fase 9.
- Membaca seperti loop lama: sheet pertama, mulai baris 1, berhenti di baris pertama yang kolom 1 (NIK) kosong.
- Tambahan: bila baris 1 kolom 1 berisi teks `NIK` (case-insensitive), baris itu dianggap judul dan dilewati
  (menjawab D-008 secara aman untuk kedua kemungkinan).
- Sel formula dibaca nilai hasil hitungnya. NIK numerik disimpan sebagai string tanpa `.0`/notasi eksponen.

## D-028 — Validasi file upload · accepted · Fase 5
Plan menyebut `mimes:xls,xlsx`. Deteksi MIME file `.xls` (format CDF/OLE) tidak konsisten antar server
(`application/vnd.ms-excel`, `application/CDFV2`, `application/octet-stream`), sehingga dipakai
`extensions:xls,xlsx` + `max:10240`. Isi file divalidasi oleh PhpSpreadsheet yang **dibatasi ke reader Xls/Xlsx**
(tidak jatuh ke reader CSV/HTML); file yang tidak bisa dibaca → error "File tidak bisa dibaca". File upload tidak
disimpan (dibaca dari temp upload PHP), sama seperti lama yang menghapusnya setelah import.

## D-029 — Warning import · accepted · Fase 5
Warning tidak memblokir dan disimpan di kolom baru `payslip_batches.warnings` (json) supaya tetap tampil di
halaman preview:
- THP Excel ≠ total penerimaan − total potongan (sesuai plan).
- NIK duplikat dalam satu file (lama tidak memeriksa; dua baris = dua email).
Error validasi per baris memakai label PDF + nomor kolom, mis. `Baris 4 (NIK 1004): GAJI (kolom 5) harus berupa angka.`

## D-030 — Manajemen data PT belum ada di plan · accepted (dikerjakan di Fase 8, lihat D-039) · Fase 8
Kode lama punya CRUD `Tmstbrand`. Plan tidak mencantumkan CRUD `companies`; data PT hanya datang dari
`eslip:import-legacy`. Field baru (`mailer`, `from_address`, `pdf_password_field`, `logo_path`, `city`) tidak
punya padanan di `tmstbrand`, jadi butuh layar edit. Usulan: tambahkan CRUD company (SuperAdmin/Admin) di Fase 8.
Sementara itu `DatabaseSeeder` membuat 2 PT contoh (Standard & XFT, mailer `log`) untuk development.

## D-031 — Hak upload · accepted · Fase 5
Sesuai lama, semua role (termasuk `user`) boleh upload dan melihat preview batch.

## D-032 — Library PDF & password · accepted (default, belum dikonfirmasi user) · Fase 6
User tidak memilih saat ditanya, jadi dipakai default plan + rekomendasi:
- **Library: `tecnickcom/tcpdf` ^6.11** (bukan FPDF + fpdi-protection). Alasan: enkripsi **AES-256** (`AESV3`),
  API `Cell()/Line()` hampir sama dengan FPDF sehingga layout bisa di-port baris per baris.
  Versi 7.0.x baru dirilis (7.0.22 rilis minggu ini) → dipin ke cabang 6.x yang matang.
  Catatan: TCPDF ditandai *deprecated* oleh pembuatnya (penerus: `tecnickcom/tc-lib-pdf`). Semua pemakaian
  terkurung di `App\Payslip\Pdf\PayslipDocument` + `GeneratePayslipPdf`, jadi migrasi library nanti terlokalisir.
- **Password: NIK** (default plan), per company lewat `companies.pdf_password_field` = `nik` | `email` | `none`.
  Owner password acak per file; izin yang dicabut: modify, annot-forms, fill-forms, assemble (print & copy tetap boleh).
  Diverifikasi dengan poppler `pdftotext`: tanpa password/password salah ditolak, NIK membuka file.
- **Preview admin** (`GET /payslip-batches/{batch}/payslips/{payslip}/pdf`) di-stream inline **tanpa password**
  dan tanpa disimpan, `Cache-Control: private, no-store`. File yang dikirim email selalu ber-password.
- File untuk email disimpan sementara di disk `local` (private, `storage/app/private`) pada
  `payslips/tmp/{batch_id}/{nik}-{payslip_id}.pdf`; dihapus oleh job (Fase 7) dan cleanup terjadwal (Fase 9).

## D-033 — Port layout PDF · accepted · Fase 6
- A5 landscape, margin 10 mm, cell padding 1 mm (meniru `cMargin` FPDF), font Helvetica (pengganti Arial di FPDF).
- Isi kolom dibangun generik dan menghasilkan urutan baris **identik** dengan kedua slip lama:
  kiri = earnings + `KET PENERIMAAN :` + catatan; kanan = deductions + `KET POTONGAN :` + catatan + 4 baris kehadiran.
- Posisi garis total dihitung dari posisi Y saat itu (D-010), bukan hardcode 115/105.
- Garis bawah header digeser dari y=30 ke y=31 karena baris telepon menempel garis.
- Logo dibaca dari `companies.logo_path` di disk `local` (tinggi 14 mm); tanpa logo, header tetap di X=25 seperti lama.
- Periode `Oktober 2026` dan tanggal `Bandung, 31 Oktober 2026` (D-009). Kota kosong → hanya tanggal.

## D-034 — Format username · accepted · Fase 3 (fix)
Rule `alpha_dash` menolak titik, padahal username lama mungkin memakai titik (`budi.priyo`).
Diganti `regex:/\A[A-Za-z0-9._-]+\z/`.

## D-035 — Hosting & akun SMTP · open · Fase 7/9
User belum menjawab. Asumsi sementara: **VPS** (worker via Supervisor, contoh di `docs/deploy/supervisor.conf`).
Mailer per PT memakai `companies.mailer` (`smtp_bogajabar`, `smtp_maharasa`, `smtp_gmail`, atau `log` untuk dev).
Pemetaan PT → mailer harus diisi saat import data PT (Fase 9). Jika ternyata shared hosting tanpa Supervisor,
alternatif: cron `* * * * * php artisan queue:work --stop-when-empty --max-time=55`.

## D-036 — Retry & throttle job · accepted · Fase 7
Plan: `tries = 3`. Middleware `RateLimited` me-*release* job saat kena limit, dan setiap release dihitung sebagai
attempt, sehingga `tries = 3` membuat job gagal hanya karena antre. Maka dipakai `maxExceptions = 3`
(3 kegagalan nyata) + `retryUntil = 12 jam` + `backoff = [60, 300]`. Throttle: `ESLIP_MAIL_PER_MINUTE`
(default 20) per mailer (`RateLimiter::for('payslip-mail')`, key `payslip-mail:{mailer}`).

## D-037 — Keamanan pengiriman · accepted · Fase 7
- Batch hanya bisa "Kirim semua" dari status `draft`; dicek di dalam transaksi dengan `lockForUpdate`
  (double click tidak mengirim dua kali).
- Job melewati payslip yang sudah `sent` (retry setelah sukses tidak mengirim ulang email).
- `sent_count` / `failed_count` dihitung ulang dari tabel `payslips` (`refreshDeliveryCounts()`), bukan increment,
  sehingga tetap benar saat retry dan kirim ulang. Batch jadi `done` saat tidak ada yang `pending`.
- Setiap attempt (sukses/gagal) ditulis ke `delivery_logs` (recipient, error, mailer, attempt).
- `ESLIP_MAIL_OVERRIDE_TO`: semua email dialihkan ke satu inbox (untuk paralel run Fase 9 / staging);
  alamat karyawan asli tetap tercatat di `delivery_logs.context.employee_email`.
- Semua role boleh mengirim (lama: semua role bisa menekan "proses").

## D-038 — Isi email · accepted · Fase 7
Subject `E-Slip {nama PT}` (sama dengan lama). Body `Dear {nama karyawan}, Berikut E-Slip Gaji Bulan {Oktober 2026}.`
(lama menyapa dengan alamat email, D-009) + petunjuk password bila PDF diproteksi + penutup nama PT.
Tersedia versi HTML dan teks. From = `companies.from_address` / `from_name`.

## D-039 — UI Fase 8 · accepted · Fase 8
UI memakai Blade + Tailwind (tanpa Livewire/yajra): tabel server-side dengan paginasi + filter via query string.
Cukup untuk volume batch/slip saat ini; tidak ada query di Blade (relasi di-eager-load di controller).
- **Dashboard** (`/dashboard`): daftar batch (PT, periode, template, total, terkirim, gagal, status, uploader),
  filter PT & status, periode terbaru di atas.
- **Detail batch**: filter status slip, link ke riwayat batch, hapus batch.
- **Riwayat pengiriman** (`/delivery-logs`): setiap attempt kirim, termasuk baris histori `tstatus` lama
  (`payslip_id` null) yang datanya dibaca dari `context` (kunci: `nik`, `name`, `period`, `company`) dan diberi label
  "sistem lama". Cari NIK/nama/email, filter status & batch. Semua role boleh melihat (lama: `Tstatus` terbuka
  untuk semua role).
- **CRUD PT** (`/companies`, SuperAdmin/Admin): header slip (kode, nama, alamat, kota, telepon, logo),
  template default, password PDF (`nik`/`email`/`none`), mailer (hanya mailer yang terdefinisi di
  `config/mail.php`, tanpa `failover`/`roundrobin`), email & nama pengirim. Logo PNG/JPG ≤ 1 MB disimpan di disk
  privat `logos/`. PT yang sudah punya batch tidak bisa dihapus.

## D-040 — Hapus batch · accepted · Fase 8
Padanan `deldata`/`delete` lama (hanya role selain `User`). Baru: hanya batch berstatus **draft** yang bisa dihapus
(belum ada email terkirim), payslip ikut terhapus (cascade). Batch `processing`/`done` tidak bisa dihapus agar
histori pengiriman tetap utuh.

## D-041 — `eslip:import-legacy` · accepted (belum diuji ke data asli) · Fase 9
Dibangun dari kolom yang dibaca kode lama karena dump production belum ada (D-013). Diuji dengan salinan tabel
legacy tiruan di SQLite. **Wajib `--dry-run` ke DB asli dulu.**
- **Idempotent tanpa menimpa**: user (by `username`, case-insensitive) dan PT (by `code`) yang sudah ada dilewati,
  karena admin mungkin sudah mengeditnya di aplikasi baru. `tstatus` dicocokkan lewat `context.legacy_id`,
  jadi run berikutnya hanya menambah baris baru.
- **User**: hash 40-hex → disalin + `legacy_sha1 = true`; selain itu dianggap plaintext → `Hash::make()`;
  password kosong → password acak (harus di-reset admin). Role via `UserRole::fromLegacy()`. Nama kosong → username.
- **PT** (`tmstbrand`): kode = `ST`/`PL`/`BJ`/`XF` untuk id 1–4 (dari logo hardcode lama), selain itu `BRAND-{id}`.
  Template default `standard`, override per id dengan `--template=4:xft` (pemetaan PT → template tidak ada di DB lama).
  Mailer default `--mailer=smtp_bogajabar` (satu-satunya akun di kode lama), password PDF `nik`.
  Logo disalin dari `--logo-dir` (`logo-{KODE}.png`) ke disk privat.
- **Riwayat** (`tstatus`): `status` 1 → sent, lainnya → failed. `tstatus` tidak punya timestamp, jadi
  `attempted_at` = tanggal terbit (`penerbit`), fallback periode. `namapt` (id brand) diterjemahkan ke nama PT.
- Setiap langkah dalam transaksi; `--only=users|companies|history` untuk menjalankan sebagian.

## D-042 — Cleanup & housekeeping terjadwal · accepted · Fase 9
`eslip:cleanup-temp` (harian 02:00) menghapus PDF di `payslips/tmp` yang lebih tua dari 24 jam beserta folder
kosongnya (jaring pengaman bila worker mati sebelum `finally`). `queue:prune-batches` dan `queue:prune-failed`
mingguan (30 hari). Butuh cron `schedule:run` di server.

## D-043 — Langkah Fase 9 yang belum bisa dikerjakan · open · Fase 9
Belum bisa dikerjakan tanpa akses user/server; prosedur lengkap ada di `docs/deploy/CHECKLIST.md`:
- Import ke DB production (butuh dump/akses read-only, D-013).
- Paralel run 1 periode (butuh sample Excel asli, server, dan SMTP asli; pakai `ESLIP_MAIL_OVERRIDE_TO`).
- Pengujian SMTP sungguhan per akun (D-035).
- ~~Pengujian migration & test suite di PostgreSQL~~ — selesai: migrate + seed dan 229 test hijau di PostgreSQL 16 lokal (D-044).
- Template ND & LLAO (D-002, D-025).

## D-044 — Database aplikasi: PostgreSQL · accepted · Fase 2/9
Atas permintaan user, DB aplikasi baru memakai **PostgreSQL** (bukan MySQL/MariaDB seperti plan awal).
DB lama tetap MySQL dan hanya dibaca oleh `eslip:import-legacy` (koneksi `legacy`, butuh `pdo_mysql` juga).
Perbedaan perilaku yang ditangani:
- **LIKE case-sensitive** di Postgres → pencarian (`/users`, `/delivery-logs`) memakai `whereLike()` yang
  di-compile menjadi `ILIKE` di pgsql dan `LIKE` di MySQL/SQLite.
- **Kolom `json` tidak bisa di-LIKE** di Postgres → pencarian histori lama memakai `context::text`.
- **Username**: collation MySQL lama case-insensitive (login `Admin` = `admin`). Dipertahankan: login mencari
  `lower(username)`, validasi unik memakai `App\Rules\UniqueUsername`, dan migration baru menambah unique index
  `lower(username)` untuk pgsql/sqlite.
- Tipe `json` Postgres menyimpan teks apa adanya; D-011 (urutan label dari Template) tetap berlaku.
- Diverifikasi di PostgreSQL 16 lokal: `migrate --seed` dan seluruh test suite hijau. Test tidak boleh
  mengandalkan id hardcode (sequence Postgres tidak di-reset saat rollback transaksi test).
- Menjalankan test ke Postgres: `DB_CONNECTION=pgsql DB_DATABASE=<db_test> php artisan test`
  (pakai database terpisah; RefreshDatabase mengosongkan isinya).

## D-045 — Perbaikan dari review pra-trial · accepted · Fase 9
- **`retry_after` queue database 90 → 180 detik.** Nilai lama lebih kecil dari `SendPayslipJob::$timeout`
  (120 dtk): jika SMTP lambat, job yang masih berjalan diambil lagi oleh worker lain → slip terkirim dua kali.
  Bisa diubah lewat `DB_QUEUE_RETRY_AFTER`, wajib > 120. Ada test penjaga.
- **Dispatch gagal tidak lagi membuat batch macet.** Status `processing` di-set di transaksi sebelum Bus batch
  disimpan; jika penyimpanan batch gagal, status batch dan slip dikembalikan (draft / failed) agar bisa dicoba lagi.
- **`eslip:create-user`**: seeder memakai factory (Faker = dev dependency), sehingga tidak bisa dipakai di image
  production; perintah ini membuat akun pertama.
- README berisi sisa teks UTF-16 (`# eslipv2`) di akhir file — dibersihkan.

## D-046 — Docker untuk production · accepted · Fase 9
Docker hanya untuk server; development lokal tetap tanpa Docker. Satu image/container `eslipv2`
(`Dockerfile` multi-stage: vendor → assets → php-fpm 8.4 + nginx + supervisord) yang menjalankan web,
queue worker, dan scheduler. Entrypoint meng-cache config dari `.env.docker` (tidak di-commit) lalu
`migrate --force` (`RUN_MIGRATIONS`). Panduan: `docs/deploy/DOCKER.md`.
- PostgreSQL tidak ikut di stack: memakai container `maharasa-postgres` yang sudah ada (database `eslip_v2`
  terpisah) lewat `host.docker.internal:5432`, agar tidak bergantung pada nama network-nya.
- Port 8004 (8000–8003 & 8080 sudah dipakai), domain & SSL lewat nginx-proxy-manager.
- Tanpa Mailpit: trial memakai SMTP asli + `ESLIP_MAIL_OVERRIDE_TO`.
- Image belum di-build di mesin development (Docker tidak terpasang) — build pertama di server.
