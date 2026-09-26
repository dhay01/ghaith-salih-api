#!/usr/bin/env bash
set -euo pipefail

# Run on the production host after CI tests pass.
# .env, storage/app, and database/*.sqlite are gitignored and survive reset.

cd /var/www/ghaith-salih-api

git fetch origin main
git reset --hard origin/main

export COMPOSER_MEMORY_LIMIT=-1
/usr/local/bin/composer install \
  --no-dev --optimize-autoloader --no-interaction --prefer-dist

php artisan migrate --force
php artisan storage:link --force
php artisan optimize
php artisan queue:restart
sudo systemctl reload php8.5-fpm
