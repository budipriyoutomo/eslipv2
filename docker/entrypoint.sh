#!/bin/sh
set -e

cd /var/www/html

# Only the default command (supervisord) prepares the app; `docker compose run ... php artisan x` skips it.
if [ "$1" = "supervisord" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "APP_KEY belum diisi di .env.docker. Buat dengan:" >&2
        echo "  docker compose run --rm --no-deps app php artisan key:generate --show" >&2
        exit 1
    fi

    # A fresh named volume on storage/ may be missing these; files must belong to www-data.
    mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs
    chown -R www-data:www-data storage bootstrap/cache

    # Cache config/routes/views/events from the container environment, then migrate.
    su-exec www-data php artisan optimize --quiet

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        su-exec www-data php artisan migrate --force --no-interaction
    fi
fi

exec "$@"
