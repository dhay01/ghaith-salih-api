#!/usr/bin/env bash
set -euo pipefail

# Run on the production host after CI tests pass.
# .env, storage/app, and database/*.sqlite are gitignored and survive reset.

cd /home/ghaith/ghaith-salih-api

git fetch origin main
git reset --hard origin/main

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
export COMPOSER_MEMORY_LIMIT=-1

"$PHP" /usr/local/bin/composer install \
  --no-dev --optimize-autoloader --no-interaction --prefer-dist

"$PHP" artisan migrate --force
"$PHP" artisan storage:link --force
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart
