# Deploy & Cutover Checklist — E-Slip v2

Urutan kerja untuk memindahkan e-slip dari CodeIgniter lama (`htdocs/eslip`) ke Laravel 13.
Centang setiap langkah dan catat hasilnya. Detail keputusan ada di `docs/DECISIONS.md`.

---

## 1. Server

- [ ] PHP **8.4** dengan ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `iconv`, `intl`, `mbstring`,
      `openssl`, `pdo_mysql`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`.
      Cek: `php -m`. (`intl` tidak dipakai langsung oleh kode saat ini, tetapi disarankan plan.)
- [ ] MySQL 8 / MariaDB 10.6+ — database baru `eslip_v2` (jangan pakai database lama).
- [ ] Composer 2, Node 20+ (hanya untuk build asset; bisa build di CI/lokal lalu upload `public/build`).
- [ ] Web root mengarah ke `public/`.
- [ ] HTTPS aktif.

## 2. Aplikasi

```sh
git clone <repo> /var/www/eslip && cd /var/www/eslip
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# isi .env: APP_URL, APP_ENV=production, APP_DEBUG=false, DB_*, LEGACY_DB_*, MAIL_* per mailer
npm ci && npm run build
php artisan migrate --force
php artisan storage:link   # tidak wajib: semua file sensitif ada di disk privat
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- [ ] `storage/` dan `bootstrap/cache/` bisa ditulis user web (`chown -R www-data:www-data storage bootstrap/cache`).
- [ ] `APP_DEBUG=false` di production.
- [ ] **Rotasi password SMTP lama** — password akun HRD tersimpan plaintext di source CodeIgniter (D-003).
- [ ] Isi kredensial `MAIL_BOGAJABAR_*` / `MAIL_MAHARASA_*` / `MAIL_GMAIL_*` (Gmail: App Password).
- [ ] `ESLIP_MAIL_PER_MINUTE` sesuai limit SMTP hosting (default 20/menit per akun).

## 3. Queue worker & scheduler

- [ ] Supervisor: salin `docs/deploy/supervisor.conf`, sesuaikan path/user, lalu
      `supervisorctl reread && supervisorctl update && supervisorctl start eslip-worker:*`.
- [ ] Cron scheduler (cleanup PDF sementara harian, prune batch mingguan):
      `* * * * * cd /var/www/eslip && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Setiap deploy berikutnya: `php artisan queue:restart`.
- [ ] Shared hosting tanpa Supervisor (D-035): ganti worker dengan cron
      `* * * * * php /var/www/eslip/artisan queue:work database --queue=payslips,default --stop-when-empty --max-time=55`.

## 4. Migrasi data lama

1. [ ] Siapkan akses read-only ke DB lama di `.env` (`LEGACY_DB_*`).
2. [ ] Dry run dan baca laporannya:
   ```sh
   php artisan eslip:import-legacy --dry-run --template=4:xft
   ```
   Perhatikan catatan `password lama tersimpan plaintext` / `password kosong` (D-021).
3. [ ] Jalankan import. Sesuaikan peta template (`--template=<id tmstbrand>:<standard|xft|nd|llao>`),
       mailer default, dan folder logo lama (`htdocs/eslip/image`):
   ```sh
   php artisan eslip:import-legacy --template=4:xft --mailer=smtp_bogajabar \
       --from-address=hrd@<domain> --logo-dir=/path/to/htdocs/eslip/image
   ```
   Aman dijalankan ulang: user/PT yang sudah ada tidak ditimpa, `tstatus` hanya menambah baris baru.
4. [ ] Menu **PT**: periksa setiap PT — kota (tempat terbit), mailer, email & nama pengirim, password PDF, logo.
5. [ ] Login dengan 1 akun lama untuk memastikan rehash sha1 → bcrypt berjalan.
6. [ ] Menu **Riwayat**: histori lama tampil dengan label "sistem lama".

## 5. Paralel run (1 periode gaji)

Tujuan: membuktikan slip baru identik dengan slip lama sebelum sistem lama dimatikan.

1. [ ] Set `ESLIP_MAIL_OVERRIDE_TO=<inbox-test@domain>` di `.env`, lalu `php artisan config:cache && php artisan queue:restart`.
       Semua email dari sistem baru masuk ke inbox test; alamat karyawan asli tetap tercatat di riwayat.
2. [ ] Proses periode yang sama di sistem lama (seperti biasa) dan di sistem baru (upload file Excel yang sama).
3. [ ] Di sistem baru: upload harus lolos tanpa error. Catat semua **peringatan** (THP tidak cocok, NIK duplikat).
4. [ ] Bandingkan per karyawan (sampel minimal 10 orang per template, plus semua baris yang memberi peringatan):
   - [ ] NIK, nama, jabatan, periode
   - [ ] setiap nominal penerimaan & potongan, TOTAL PENERIMAAN, TOTAL POTONGAN, TAKE HOME PAY
   - [ ] keterangan penerimaan/potongan, jumlah sakit/alpha/cuti dadakan/kehadiran
   - [ ] kota & tanggal terbit
5. [ ] Buka PDF dari inbox test dengan password NIK; pastikan tanpa password tidak bisa dibuka.
6. [ ] Catat perbedaan yang disengaja (D-009, D-033): bulan Bahasa Indonesia, sapaan dengan nama, font Helvetica.
7. [ ] Hasil paralel run disetujui HR → kosongkan `ESLIP_MAIL_OVERRIDE_TO`, `config:cache`, `queue:restart`.

## 6. Cutover

- [ ] Umumkan freeze: tidak ada pengiriman di sistem lama mulai tanggal X.
- [ ] Jalankan ulang `eslip:import-legacy` untuk mengambil user & riwayat terakhir.
- [ ] Sistem lama: matikan akses (mis. `deny from all`), **jangan drop database** sampai masa simpan selesai.
- [ ] Hapus file sisa di `htdocs/eslip/temp_send/` dan `temp_upload/` (berisi slip gaji).
- [ ] Monitor periode pertama: menu Dashboard (gagal = 0), `storage/logs/laravel.log`, `storage/logs/worker.log`.

## 7. Rollback

Sistem lama tidak diubah dan database-nya tetap ada. Jika periode pertama bermasalah: hentikan worker
(`supervisorctl stop eslip-worker:*`), aktifkan kembali akses sistem lama, kirim dari sistem lama.
Batch yang sudah terkirim sebagian di sistem baru bisa dilihat di menu Riwayat agar tidak terkirim dua kali.
