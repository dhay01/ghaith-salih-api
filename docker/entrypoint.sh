#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Railway assigns the port at run time and only announces it through $PORT.
# Substituted with sed rather than envsubst so nginx's own $uri / $query_string
# variables survive.
sed "s/\${PORT}/${PORT:-8080}/g" \
  /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# A mounted volume arrives empty and root-owned, so these are recreated on
# every boot rather than baked into the image.
mkdir -p \
  storage/app/public \
  storage/app/private \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
  chown www-data:www-data "$DB_FILE"
fi

# public/storage is a symlink into storage/app/public and lives on the image's
# own layer, so it has to be re-pointed each boot.
php artisan storage:link --force

php artisan migrate --force

# Cached here rather than at build time: every value these bake in comes from
# Railway's environment, which the build never sees.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec supervisord -c /etc/supervisor/supervisord.conf
