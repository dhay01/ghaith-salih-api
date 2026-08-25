# ghaith salih — API & dashboard

Laravel backend for the photographer portfolio. Two surfaces:

- **`/api/*`** — public, unauthenticated JSON consumed by the Vue SPA.
- **`/admin`** — Filament dashboard. The only place anyone logs in; the
  portfolio itself has no accounts and no login.

Laravel 13 · Filament 5 · PHP 8.5.

## Running it

```sh
composer install
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
php artisan queue:work       # required — emails are queued
```

Create a dashboard user, then grant it panel access — the two are separate steps
on purpose:

```sh
php artisan make:filament-user            # creates the account (no panel access)
php artisan admin:grant you@example.com   # grants access to /admin
php artisan admin:grant you@example.com --revoke
```

### Who can reach `/admin`

`users.is_admin` gates the panel, enforced by `canAccessPanel()` on the `User`
model. A row in `users` is **not** enough — without the flag, login is rejected
with the same "credentials do not match" message as a wrong password, so the
panel never confirms whether an account exists.

`is_admin` is not mass-assignable, and `admin:grant --revoke` refuses to remove
the last admin. `tests/Feature/AdminPanelAccessTest.php` guards all of this.

This matters because the dashboard lists every applicant's name, phone and
email, and it is the only authenticated surface in the system.

### Two-factor authentication

Available per user, not enforced: an admin can enable an authenticator app from
**`/admin/profile`**, with eight recovery codes. To make it mandatory for
everyone, flip `isRequired: true` in `AdminPanelProvider`.

### Database

Local development runs on **SQLite** (`database/database.sqlite`) because it
needs no credentials. Production is intended for **MySQL** — flip
`DB_CONNECTION=mysql` in `.env` and fill in the commented block beneath it.

### Mail

`MAIL_MAILER=log` by default, so mail lands in `storage/logs/laravel.log`
instead of being delivered. Set real SMTP credentials and `MAIL_ADMIN_ADDRESS`
(who receives the internal "new reservation" notification) before going live.

## API

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/workshops` | Published workshops, soonest first |
| `GET` | `/api/workshops/{slug}` | One workshop with full detail |
| `POST` | `/api/workshops/{slug}/reservations` | Public, throttled 5/min and 20/day per IP |

Locale is resolved per request from `?locale=` first, then `Accept-Language`,
falling back to English. Translatable fields are flattened to plain strings in
the response, so the SPA never sees the per-locale storage shape.

CORS is restricted to the origins listed in `FRONTEND_ORIGINS` rather than `*`,
because the reservation endpoint accepts writes without authentication.

## Bilingual content

Content columns store `{"en": "…", "ar": "…"}` via `spatie/laravel-translatable`.
Arabic is wired up throughout but intentionally left unpopulated — content is
authored in English for now, and the fallback locale covers the gap. Adding
Arabic later is a content task, not a migration.

Locales are declared in `config/localization.php`.

## Reservations

The intake questions live in `config/reservation_questions.php`, which is the
server-side source of truth: it drives validation, the dashboard's answer
rendering, and the CSV export. It mirrors the frontend's
`src/data/reservationQuestions.js` — **change both together.**

Two deliberate design choices:

- **Identity is lifted into real columns** (`name`, `email`, `phone`, `age`,
  `gender`) while the full questionnaire is kept in the `answers` JSON. The
  columns are what you filter, dedupe and export on; the JSON means nothing is
  lost when the questions change.
- **Answers are stamped with `question_set_version`.** Editing the form later
  adds a new version rather than silently reinterpreting old submissions.

Seats are counted from live reservations rather than a denormalised counter, and
the workshop row is locked for the duration of the insert, so two simultaneous
submissions cannot oversell the room — the loser is waitlisted automatically.

Submitting sends two queued emails: a confirmation to the applicant (if they
gave an address) and an internal notification with the full questionnaire.

## Not built yet

The shop. Everything else the frontend renders — photos, gallery categories, blog
posts, workshops, page copy, site settings and the About page — is served from
this API and editable in the dashboard.

## Deploying

Two things ship separately: this API (needs PHP) and the Vue site (static files
after a build). They talk over HTTPS and share nothing else.

### Server prerequisites

```sh
apt install php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl \
            libvips-tools nginx
```

`libvips-tools` is not optional: without it every photo upload larger than 25 MB
fails, and deep zoom never builds.

Raise two PHP limits so uploads can arrive:

```ini
upload_max_filesize = 16M
post_max_size = 16M
```

These bound one **chunk**, not the whole file — the dashboard slices large
originals into 4 MB pieces, so a 3 GB panorama uploads fine under these settings.

### The API

```sh
git clone <repo> && cd ghaith-salih-api
composer install --no-dev --optimize-autoloader

cp .env.example .env          # then edit every line marked PRODUCTION
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # categories and page copy only; no demo content
php artisan storage:link

php artisan make:filament-user            # create the dashboard account
php artisan admin:grant you@example.com   # then grant it panel access

php artisan config:cache && php artisan route:cache
```

Point nginx at `public/`, and make `storage/` and `database/` writable by the
web server user.

**A queue worker must run permanently.** Without it nothing is tiled and no
reservation email is ever sent. As a systemd unit:

```ini
[Service]
User=www-data
ExecStart=/usr/bin/php /var/www/api/artisan queue:work --tries=1 --timeout=1800
Restart=always
```

The timeout must exceed `GIGAPIXEL_TIMEOUT`, or long tiling jobs are killed
mid-run.

### The frontend

```sh
VITE_API_URL=https://api.example.com npm run build
```

Upload `dist/` to any static host. Requires **Node 22.12+** — Vite will not run
on Node 21.

`VITE_API_URL` is compiled into the bundle, so changing the API address later
means rebuilding, not editing a server setting.

### Database

SQLite is the default and is appropriate for a low-traffic site: one file at
`database/database.sqlite`, and a backup is a file copy. It must be writable, and
the `database/` directory must be writable too so SQLite can create its journal.

Switch to MySQL by setting `DB_CONNECTION=mysql` and the `DB_*` block. Worth doing
if the dashboard ever has several people uploading at once, since SQLite locks the
whole file on write.

### What does not travel with the code

`storage/app/public` holds every uploaded photo and every generated tile — for a
site with real content that is hundreds of megabytes, and it is **not in git**.
Copy it across with `rsync`, or re-upload the photos and let tiling regenerate.

### Before it goes public

- `APP_DEBUG=false` — otherwise errors print stack traces and environment values
- `FRONTEND_ORIGINS` lists the real site address, or every API call is blocked
- `APP_URL` is the API's real address, or every image 404s
- `MAIL_MAILER` is not `log`, or no reservation email is ever delivered
- The dashboard password is not the development one

## Gigapixel deep zoom

Photos flagged **Deep zoom** are sliced into a Deep Zoom tile pyramid by a queued
job, and the site's lightbox renders them with OpenSeadragon. A viewer downloads
only the handful of tiles covering what is on screen, so a 455 MP image opens in
about nine small requests instead of an 81 MB download.

### What the server needs

```sh
brew install vips          # macOS
apt install libvips-tools  # Debian / Ubuntu
```

`vips` does two jobs here, and both matter because it streams an image in strips
rather than decoding it whole:

1. **Tiles.** `vips dzsave` writes the pyramid.
2. **Web-sized versions.** Any upload over `gigapixel.large_file_bytes` (25 MB by
   default) skips the usual GD thumbnails entirely — GD would need many gigabytes
   of memory for a gigapixel original — and vips produces thumb/preview/full
   instead.

**A queue worker must be running or nothing is ever tiled:**

```sh
php artisan queue:work
```

### Upload limits — why they can stay small

Large originals are uploaded from the dashboard in pieces: the browser slices the
file and posts one chunk at a time to `admin/large-upload/*`, and the server
appends them. **PHP's limits therefore only need to exceed the chunk size, not the
file size** — the default chunk is 8 MB, so 16 MB is comfortable no matter how
large the original is:

```ini
upload_max_filesize = 16M
post_max_size = 16M
```

Change the chunk size with `LargeFileUpload::make(...)->chunkSize(...)` if a host
imposes something smaller.

The ordinary image field still posts in one request, so it remains bound by those
same limits — which is why the **Large original** section exists alongside it on
the photo edit screen.

### Storage

Tiles are written to `gigapixel.disk` (the `public` disk by default) under
`tiles/<slug>`. Rough figures measured on real images:

| Original | Tiles | Size | Time |
|---|---|---|---|
| 75 MP | 419 | 21 MB | ~1 s |
| 455 MP | 2,393 | 118 MB | ~18 s |

Expect tiles to weigh roughly 1.5x the original, and a full gigapixel image to
produce around 5,000 files. If the host wipes its filesystem on deploy, point
`GIGAPIXEL_DISK` at an S3-compatible disk instead — no code changes needed.

### Why the viewer never fetches the .dzi

The API returns the tile geometry as JSON and the frontend builds the tile source
from it. Fetching the `.dzi` descriptor would be an XHR and therefore subject to
CORS, while the tiles themselves are plain `<img>` requests that are not — and web
servers serve `/storage` as static files without invoking PHP, so CORS headers
there would need web-server configuration. Sending the geometry as data avoids all
of it.

### Re-tiling

Replacing a photo's image re-slices it automatically; renaming does not. The photo
list also has a **Rebuild deep zoom** action, and a status column showing
Queued / Building… / Ready / Failed with the error on hover.
