#!/bin/sh
# Prepares the shared SQLite database and then runs the container's command.
set -e
mkdir -p "$(dirname "${DB_DATABASE:-/app/database/data/database.sqlite}")"
touch "${DB_DATABASE:-/app/database/data/database.sqlite}"
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi
exec "$@"
