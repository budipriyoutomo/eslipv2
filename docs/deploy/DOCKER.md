# Docker — production (satu container)

Docker hanya dipakai di server; development lokal tetap tanpa Docker (lihat README).

Container `eslipv2` menjalankan semuanya lewat supervisord:

| Proses | Fungsi |
|---|---|
| nginx + php-fpm | Web, port host **8004** (8000–8003 & 8080 sudah dipakai container lain) |
| `worker` | `queue:work` — mengirim e-slip (queue `payslips`) |
| `scheduler` | `schedule:work` — cleanup PDF sementara & prune queue (D-042) |

Setiap kali container start: config/route/view di-cache dari `.env.docker`, lalu `migrate --force`
(matikan dengan `RUN_MIGRATIONS=false`). File upload, logo PT, dan log ada di volume `eslipv2_storage`.

**PostgreSQL memakai container yang sudah ada, `maharasa-postgres`**: container `eslipv2` ikut network
external `posgresql_default` sehingga `DB_HOST=maharasa-postgres`. Port 5432 tidak perlu dibuka ke publik.

## 1. Siapkan database di `maharasa-postgres` (sekali)

Ganti `postgres` dengan superuser container itu jika berbeda:

```sh
docker exec -it maharasa-postgres psql -U postgres -c "CREATE USER eslip WITH PASSWORD 'ganti-password-ini';"
docker exec -it maharasa-postgres psql -U postgres -c "CREATE DATABASE eslip_v2 OWNER eslip ENCODING 'UTF8';"
```

## 2. Jalankan

```sh
cp .env.docker.example .env.docker
# isi DB_PASSWORD, SMTP (MAIL_<AKUN>_*), dan untuk trial ESLIP_MAIL_OVERRIDE_TO=<inbox test>
docker compose build
docker compose run --rm app php artisan key:generate --show    # tempel hasilnya ke APP_KEY

docker compose up -d
docker compose logs -f                                          # tunggu "migrated" / supervisord running

# Akun login pertama (password ditanya)
docker compose exec -u www-data app php artisan eslip:create-user superadmin --name="Super Admin"
```

Buka `http://<server>:8004`. Selama trial isi `ESLIP_MAIL_OVERRIDE_TO`, sehingga semua slip masuk ke inbox test
(alamat karyawan asli tetap tercatat di Riwayat).

Jika `docker compose up` gagal dengan *network posgresql_default declared as external, but could not be found*,
cek nama network yang benar: `docker inspect maharasa-postgres --format '{{json .NetworkSettings.Networks}}'`
lalu sesuaikan `name:` di bagian `networks` pada `docker-compose.yml`.

## 3. Domain (nginx-proxy-manager)

Proxy Host baru: domain e-slip → `http://<IP host>:8004`, aktifkan SSL. Lalu di `.env.docker`:
`APP_URL=https://<domain>`, `SESSION_SECURE_COOKIE=true`, kemudian `docker compose up -d`.

## Perintah harian

Jalankan artisan sebagai `www-data` agar file cache/log tidak dimiliki root:

```sh
docker compose logs -f                                   # web, worker, scheduler
docker compose exec -u www-data app php artisan <cmd>
docker compose up -d                                     # setelah ubah .env.docker
git pull && docker compose up -d --build                 # update kode (migrate otomatis)
```

Import data lama — MySQL 8 di server ini dipublish di port host 3308 (`LEGACY_DB_PORT=3308`); pastikan itu memang
database e-slip lama:

```sh
docker compose exec -u www-data app php artisan eslip:import-legacy --dry-run --template=4:xft
```

Untuk `--logo-dir`, mount folder logo lama ke container lebih dulu (mis. `- /path/eslip/image:/legacy-image:ro`).

## Backup

- Database: `docker exec maharasa-postgres pg_dump -U eslip eslip_v2 > eslip_v2.sql`
- File: volume `eslipv2_storage` (logo PT; PDF slip hanya sementara).
- `DB_QUEUE_RETRY_AFTER` harus tetap > 120 detik (timeout `SendPayslipJob`), lihat D-045.
