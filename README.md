# E-Slip v2

Aplikasi pengiriman slip gaji (e-slip) per email: admin upload file Excel per PT dan periode, sistem membuat
PDF slip ber-password untuk setiap karyawan, lalu mengirimnya lewat antrean (queue) dengan log pengiriman.

Migrasi dari aplikasi CodeIgniter 3 lama ke **Laravel 13 / PHP 8.4**.

| Dokumen | Isi |
|---|---|
| [`Migration-Plan.md`](Migration-Plan.md) | Rencana migrasi per fase + progress |
| [`docs/DECISIONS.md`](docs/DECISIONS.md) | Keputusan teknis & perbedaan dengan sistem lama |
| [`docs/deploy/CHECKLIST.md`](docs/deploy/CHECKLIST.md) | Deploy, migrasi data, paralel run, cutover |
| [`docs/deploy/supervisor.conf`](docs/deploy/supervisor.conf) | Contoh queue worker |

## Alur

1. **Upload** (`/payslip-batches/create`): pilih PT, template (default ikut PT), periode, tanggal terbit, file `.xls`/`.xlsx`.
   Semua baris divalidasi; jika ada error tidak ada data yang disimpan.
2. **Preview** batch: total per karyawan, peringatan (THP tidak cocok, NIK duplikat), preview PDF.
3. **Kirim semua** → satu job per karyawan (`SendPayslipJob`) dalam satu Bus batch, dibatasi per akun SMTP.
4. **Riwayat** (`/delivery-logs`) dan **Kirim ulang yang gagal** per batch.

Template yang didukung: **Standard** dan **XFT**. ND & LLAO menunggu source/sample (D-002, D-025).

## Development

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
# development cepat dengan SQLite:
#   DB_CONNECTION=sqlite  (hapus DB_HOST/DB_DATABASE dll.)
php artisan migrate --seed          # user: superadmin / password, 2 PT contoh (mailer "log")
npm run build
php artisan serve
php artisan queue:work database --queue=payslips,default   # terminal kedua
```

Email di development masuk ke `storage/logs/laravel.log` (mailer `log`).

## Quality gate

```sh
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
```

## Perintah artisan

| Perintah | Fungsi |
|---|---|
| `eslip:import-legacy` | Import user, PT, dan riwayat `tstatus` dari DB lama (idempotent, `--dry-run`) |
| `eslip:cleanup-temp` | Hapus PDF sementara > 24 jam (terjadwal harian) |
