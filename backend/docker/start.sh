#!/bin/sh
set -eu

php artisan package:discover --ansi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
