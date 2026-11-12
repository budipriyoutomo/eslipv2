# Docker — trial & server tunggal

`docker-compose.yml` menjalankan seluruh aplikasi dalam satu host:

| Service | Isi |
|---|---|
| `db` | PostgreSQL 16 (volume `pgdata`) |
| `migrate` | Sekali jalan: `php artisan migrate --force`, lalu berhenti |
| `app` | PHP 8.4-FPM (Laravel) |
| `web` | nginx → `http://localhost:8080` (ubah dengan `APP_PORT`) |
| `worker` | `queue:work` — mengirim e-slip (queue `payslips`) |
| `scheduler` | `schedule:work` — cleanup PDF sementara & prune queue (D-042) |
| `mailpit` | Inbox trial → `http://localhost:8025`. Semua email tertangkap di sini, tidak sampai ke karyawan |

File upload, logo PT, dan log ada di volume `storage` (dipakai bersama `app`, `worker`, `scheduler`).

## Mulai trial

```sh
cp .env.docker.example .env.docker
# 1. Ganti DB_PASSWORD dan POSTGRES_PASSWORD (harus sama)
# 2. Buat APP_KEY lalu tempel ke .env.docker:
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show

docker compose up -d --build
docker compose ps                     # migrate = exited (0), lainnya running

# Akun login pertama (password ditanya)
docker compose exec app php artisan eslip:create-user superadmin --name="Super Admin"
```

Buka `http://localhost:8080`, login, buat PT di menu **PT** (mailer mana pun — di trial semuanya mengarah ke
Mailpit), upload Excel, kirim, lalu lihat emailnya di `http://localhost:8025`.

## Perintah harian

```sh
docker compose logs -f worker                 # proses pengiriman
docker compose exec app php artisan <cmd>     # artisan
docker compose restart worker                 # setelah ubah .env.docker (config di-cache saat container start)
docker compose up -d --build                  # setelah update kode (migrate jalan otomatis)
```

Import data lama (DB MySQL di host = `host.docker.internal`; di Linux tambahkan
`extra_hosts: ["host.docker.internal:host-gateway"]` pada service yang menjalankan perintah):

```sh
docker compose exec app php artisan eslip:import-legacy --dry-run --template=4:xft
```

Untuk `--logo-dir`, mount folder logo lama ke container `app` lebih dulu (mis. `- /path/eslip/image:/legacy-image:ro`).

## Menuju production

- Hapus service `mailpit`; isi `MAIL_<AKUN>_*` dengan SMTP asli. Untuk paralel run isi `ESLIP_MAIL_OVERRIDE_TO`.
- `APP_URL` = domain asli; pasang HTTPS di reverse proxy depan `web` dan set `SESSION_SECURE_COOKIE=true`.
- Jangan expose port `db`. Backup volume `pgdata` (`pg_dump`) dan `storage`.
- `DB_QUEUE_RETRY_AFTER` harus tetap > 120 detik (timeout `SendPayslipJob`), lihat D-045.
