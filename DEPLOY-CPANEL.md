# Deploying to cPanel

Written for shared cPanel hosting, which has no Docker, no root and no
long-running processes. The `Dockerfile` and `docker/` directory are for
container hosts and are simply unused here — leave them, they cost nothing.

## Before anything else

Upload `hostcheck.php` to `public_html`, open it in a browser, read it, then
delete it. It answers every question below in one go. The two that matter:

- **PHP 8.4.1 or newer.** Symfony 8 requires it. On an older PHP the site does
  not boot at all — it fails in `vendor/autoload.php` with a `platform_check.php`
  error that does not mention the version in an obvious way.
- **GD compiled with WebP.** Every image conversion this app makes is `.webp`.
  Without it, photo uploads fail with `Call to undefined function imagewebp()`.

## What the site loses on shared hosting

Gigapixel deep-zoom tiling shells out to the `vips` program, which is not
installed on shared hosting and cannot be added without root. Everything else
works. Uploads still succeed — the tiling job catches the failure and marks the
photo, rather than breaking the upload — but no new photo can be deep-zoomed.

If gigapixel matters, this needs a host where `vips` can be installed.

## Layout

The application must **not** live in `public_html`. Only Laravel's `public/`
directory may be web-reachable; everything else — `.env`, `storage/`, the
database — has to be out of reach.

```
/home/<user>/
  ghaith-salih-api/        <- the repository
    public/                <- point the domain's document root HERE
  public_html/             <- the front end's dist/ contents
```

In cPanel: **Domains → the API subdomain → Document Root** →
`/home/<user>/ghaith-salih-api/public`.

Laravel already ships the right `public/.htaccess`; nothing to add.

## Setup

From cPanel's Terminal, in `ghaith-salih-api`:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then edit it, see below
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # first deploy only
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

If there is no Terminal, run these from **cPanel → Cron Jobs** as one-off
commands, or ask the host to enable SSH.

`storage:link` needs to work — without it no uploaded image is reachable. If it
fails, create the symlink by hand: `public/storage` → `../storage/app/public`.

## Environment

Beyond the defaults in `.env.example`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<cpanel db>
DB_USERNAME=<cpanel user>
DB_PASSWORD=<cpanel password>

FRONTEND_ORIGINS=https://yourdomain.com
```

`APP_DEBUG=false` is not optional. On `true`, any error prints your database
credentials and `APP_KEY` to whoever triggered it.

`FRONTEND_ORIGINS` must be the site's real address with no trailing slash. The
browser blocks every API call from an origin not listed, and the site renders
empty with no visible error.

## The queue

There is no supervisor here, so the worker runs from cron instead. In
**cPanel → Cron Jobs**, every minute:

```
cd /home/<user>/ghaith-salih-api && php artisan queue:work --stop-when-empty --tries=1 >/dev/null 2>&1
```

`--stop-when-empty` is what makes this safe to run on a schedule: it drains the
queue and exits, so the jobs never pile up and cron never stacks workers.

## The front end

Build locally and upload the result — there is no Node on most shared hosting:

```bash
# in photographer-portfolio, with VITE_API_URL set to the live API
npm run build
```

Upload everything **inside** `dist/` into `public_html`. That includes the
`.htaccess` the build copies in, which is what makes `/work` and `/blog/<slug>`
survive a refresh.

`VITE_API_URL` is compiled into the JavaScript at build time, not read at run
time. Changing the API address later means rebuilding and re-uploading.

## Afterwards

- `/admin` is reachable by anyone who finds it. Use a real password.
- Check a photo upload end to end before handing the site over: it exercises
  the storage symlink, the GD WebP path and the queue in one go.
