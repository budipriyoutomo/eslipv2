#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diisi. Buat dengan: docker compose run --rm migrate php artisan key:generate --show" >&2
    exit 1
fi

# A fresh named volume on storage/ may be missing these.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

# Cache config/routes/views/events from the container environment.
php artisan optimize --quiet

exec "$@"
