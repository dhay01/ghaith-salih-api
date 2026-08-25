# ghaith salih — API & dashboard

Laravel backend for the photographer's portfolio. It has exactly two surfaces:

- **`/api/*`** — public, unauthenticated JSON, consumed by the Vue site.
- **`/admin`** — the Filament dashboard. The only place anyone logs in; the
  public site has no accounts and no login of any kind.

Laravel 13 · Filament 5 · PHP 8.3+ · SQLite (or MySQL)

The public site lives in a **separate repository** and deploys separately. The
two share nothing but HTTP.

---

## Contents

- [What still needs doing](#what-still-needs-doing) — **start here**
- [Quick start](#quick-start) — running it locally
- [How it fits together](#how-it-fits-together) — architecture
- [Running it on a server](#running-it-on-a-server) — the operator's manual
- [When something goes wrong](#when-something-goes-wrong) — symptoms and causes
- [Reference](#reference) — endpoints, commands, settings

---

## What still needs doing

The application is complete and tested. What follows is setup that can only be
done on the real server, plus a few deliberate gaps.

### Must be done, or the site is broken or unsafe

- [ ] **Run the queue worker as a service.** Deep zoom tiling and reservation
      emails are background jobs. Without a permanently running worker they queue
      up and never execute, with no error shown anywhere. See
      [The queue worker](#the-queue-worker) for the systemd unit.
- [ ] **Install `libvips-tools`.** Without it, every upload over 25 MB fails and
      deep zoom never builds.
- [ ] **Raise PHP's upload limits** to `upload_max_filesize = 16M` and
      `post_max_size = 16M`. The defaults are usually 2M, which rejects ordinary
      photos.
- [ ] **Set `APP_DEBUG=false` and `APP_ENV=production`.** While debug is on, any
      error prints stack traces, file paths and environment values to whoever
      triggered it.
- [ ] **Set `FRONTEND_ORIGINS`** to the site's real address. Until then the
      browser blocks every API call and the site renders completely empty.
- [ ] **Set `APP_URL`** to the API's real public address. Every image URL is
      built from it; wrong value means the JSON loads but no photo does.
- [ ] **Confirm the web server's document root is `public/`**, not the repository
      root. If it is wrong, `.env` becomes downloadable.
- [ ] **Change the dashboard password.** The development account
      (`creator@ghaithsalih.com`) still uses a password that has appeared in
      plain text in terminal history — treat it as already public.
- [ ] **Run `php artisan storage:link`**, or nothing under `/storage` is
      reachable.

### Should be done

- [ ] **Configure SMTP.** `MAIL_MAILER=log` writes mail to a log file instead of
      sending it, so nobody is notified of a reservation. Reservations are still
      recorded correctly in the meantime.
- [ ] **Set up backups** of `database/database.sqlite` and
      `storage/app/public/`. Neither is in git; code alone restores nothing.
- [ ] **Enable two-factor authentication** at `/admin/profile`. Not currently
      enabled on any account, and the panel exposes every applicant's name,
      phone and email.
- [ ] **Upload the real photography.** The gallery renders labelled placeholders
      wherever a photo has no image file.

### Deliberate gaps

- **The language switcher is disabled.** The interface strings exist in Arabic,
  but every translatable field in the database is still English-only, so
  switching would show a half-translated site. Filling the Arabic tabs in the
  dashboard is a content task; enabling the switcher afterwards is one line in
  the frontend (`languageEnabled` in `SiteNav.vue`).
- **The shop is not built.** The home page has a "coming soon" section for it.
- **SQLite, not MySQL.** Appropriate for this traffic; see
  [The database](#the-database) for when that changes.

---

## Quick start

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

php artisan make:filament-user            # create an account
php artisan admin:grant you@example.com   # grant it dashboard access
```

Then run **two** processes:

```sh
php artisan serve --port=8123    # the API and the dashboard
php artisan queue:work           # background jobs
```

The dashboard is at `http://127.0.0.1:8123/admin`.

`--seed` creates structure only: gallery categories and page copy. No demo
photos, posts or workshops — real content is entered through the dashboard.

> **The queue worker is not optional.** Deep zoom tiling and reservation emails
> are background jobs. Without a worker they queue up and never run, silently.
> This is the most common reason something "doesn't work".

---

## How it fits together

### The two repositories

```
photographer-portfolio   (Vue 3 + Vite)       →  the public website
ghaith-salih-api         (Laravel + Filament) →  this repo: JSON + dashboard
```

The site is a folder of static files after `npm run build`. It fetches
everything from this API at runtime. Nothing on the public site is hardcoded —
photos, page headings, blog posts, workshop details, contact information and the
About page all come from here and are editable in the dashboard.

### A request, end to end

```
visitor → yoursite.com  (static files, no PHP)
             │  fetch()
             ▼
        api.yoursite.com/api/photos
             │
        ContentController → Photo model → PhotoResource → JSON
             │
        images referenced by URL, served as static files from /storage
```

### Where things live

| Path | What it holds |
|---|---|
| `app/Models/` | The content types: `Photo`, `Post`, `Workshop`, `Category`, `Page`, `SiteSetting`, `AboutPage`, `HeroSlide`, `Reservation` |
| `app/Http/Controllers/Api/` | The public JSON endpoints |
| `app/Http/Resources/` | Shapes each model into JSON — **this is the API's contract with the site** |
| `app/Filament/` | Everything the dashboard renders |
| `app/Jobs/` | Background work (deep zoom tiling) |
| `app/Actions/` | Logic that must not live in a controller (creating a reservation) |
| `config/gigapixel.php` | Deep zoom settings |
| `config/reservation_questions.php` | The workshop intake questions |
| `tests/Feature/` | 42 tests covering access control, reservations, uploads |

### Five ideas worth understanding

**1. Translatable text.** Fields visitors read are stored as JSON per locale —
`{"en": "Misty Ranges", "ar": "..."}` — via `spatie/laravel-translatable`. Each
model lists which fields work this way in its `$translatable` property. The API
resolves them to plain strings for the requested locale, so the site never sees
the storage shape. Arabic is wired in everywhere and currently empty; filling it
is a content task, not a migration. Locales are declared in
`config/localization.php`.

**2. Images have three sizes.** Uploading a photo generates `thumb`, `preview`
and `full`, and the API returns all three plus the original. The gallery grid
uses `thumb`, the lightbox uses `full`. Anything over 25 MB skips the normal
thumbnail pipeline and is resized by vips instead, because the usual library
loads an entire image into memory and a gigapixel original would need many
gigabytes.

**3. Seat counts are derived, never stored.** A workshop's remaining seats are
calculated from live reservations, and the workshop row is locked during booking.
Overselling is therefore impossible: if seats run out mid-booking the reservation
is automatically waitlisted. Deleting a reservation returns its seats
immediately, with nothing to reconcile.

**4. The intake questions have exactly one definition.**
`config/reservation_questions.php` drives both the form the visitor sees (served
at `/api/reservation-questions`) and the validation the submission is checked
against, so the two cannot drift apart. Each submission is stamped with the
question-set version, so editing the questions later never reinterprets older
answers.

Reservations also lift identity fields (`name`, `email`, `phone`, `age`,
`gender`) into real database columns while keeping the full questionnaire as
JSON. The columns are what you filter, sort and export on; the JSON means
nothing is lost when questions change.

**5. Page copy is data.** Headings and section text for each route live in the
`pages` table, keyed by route name (`home`, `work`, `blog`, `courses`, `about`).
The site asks for `pages/blog` rather than hardcoding "notes from the field".

### Deep zoom

Photos flagged **Deep zoom** are sliced into a tile pyramid so a gigapixel
panorama can be explored in a browser. The viewer downloads only the tiles
covering what is on screen — a 455 MP image opens in about nine small requests
instead of an 81 MB download.

```
upload → queued job → vips dzsave → tiles/ → OpenSeadragon in the lightbox
```

Requires **vips** installed and **a queue worker running**. Measured figures:

| Original | Tiles | Size on disk | Time |
|---|---|---|---|
| 75 MP | 419 | 21 MB | ~1 s |
| 455 MP | 2,393 | 118 MB | ~18 s |
| ~1 gigapixel | ~5,000 | ~260 MB | a few minutes |

Expect tiles to weigh roughly 1.5× the original file.

The photo list shows live progress — *Waiting → Building 45% → Ready* — and
offers a **Rebuild deep zoom** action. Replacing a photo's image re-slices it
automatically; renaming it does not.

*Why the viewer never fetches the `.dzi` descriptor:* the API sends the tile
geometry as JSON and the frontend assembles the tile source from it. Fetching
the descriptor would be an XHR, and therefore subject to CORS, while tiles are
plain `<img>` requests that are not — and web servers serve `/storage` as static
files without invoking PHP, so CORS headers there would need web-server
configuration. Sending the geometry as data avoids the problem entirely.

### Large uploads

Gigapixel originals are far too big for a single HTTP request. The dashboard's
**Large original** field slices the file in the browser and posts it in 4 MB
pieces, which the server reassembles and verifies against the size the browser
reported.

The practical consequence: **PHP's upload limits only need to exceed one chunk**,
not the whole file. 16 MB handles a 3 GB panorama.

---

## Running it on a server

### What the server needs

```sh
apt install php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml \
            php8.3-curl php8.3-zip libvips-tools nginx
```

`libvips-tools` is required, not optional. Without it every upload over 25 MB
fails and deep zoom never builds.

Two settings in `php.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 16M
```

### Deploying

```sh
git clone <repo> && cd ghaith-salih-api
composer install --no-dev --optimize-autoloader

cp .env.example .env       # then edit every line marked PRODUCTION
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link

php artisan make:filament-user
php artisan admin:grant them@example.com

php artisan config:cache && php artisan route:cache
```

Point nginx at the `public/` directory. Make `storage/` and `database/` writable
by the web server user.

**Re-run `config:cache` and `route:cache` after every deploy**, or the app keeps
serving the previous configuration.

### The queue worker

It must run permanently and restart on failure. As a systemd unit:

```ini
[Unit]
Description=ghaith salih queue worker

[Service]
User=www-data
ExecStart=/usr/bin/php /var/www/api/artisan queue:work --tries=1 --timeout=1800
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

`--timeout` must exceed `GIGAPIXEL_TIMEOUT` (1800s by default), or long tiling
jobs are killed part-way through.

After deploying new code run `php artisan queue:restart`; workers otherwise keep
the old code in memory.

### The database

**SQLite is the default and is appropriate here.** One file at
`database/database.sqlite`, and a backup is a file copy. Two requirements:

- the file **and** the `database/` directory must be writable — SQLite writes a
  journal alongside the database
- it must sit outside the web root (it already does)

Switch to MySQL with `DB_CONNECTION=mysql` and the `DB_*` block. Worth doing only
if several people will use the dashboard at once, since SQLite locks the whole
file on write.

### Backups

Two things matter, and **neither is in git**:

| What | Where | Why |
|---|---|---|
| The database | `database/database.sqlite` | Content, reservations, accounts |
| Uploads and tiles | `storage/app/public/` | Hundreds of MB once real photos exist |

Deploying code alone gives a site with no images.

### Before it is publicly reachable

- `APP_DEBUG=false` — otherwise any error prints stack traces, file paths and
  environment values to whoever triggered it
- `APP_ENV=production`
- `FRONTEND_ORIGINS` lists the real site address — the browser blocks every API
  call from an origin not listed, and the site renders empty
- `APP_URL` is the API's real public address — every image URL is built from it
- `MEDIA_DISK=public` — never `local`, which writes to a directory the web server
  refuses to serve
- The dashboard password is not a development one
- Consider enabling two-factor authentication at `/admin/profile`; the panel
  exposes every applicant's name, phone and email

---

## When something goes wrong

| Symptom | Almost always |
|---|---|
| Site loads but is completely empty | `FRONTEND_ORIGINS` does not list the site's address (CORS). Check the browser console |
| JSON works but every image is broken | `APP_URL` is wrong, or `storage:link` was never run |
| An uploaded photo shows as an empty tile | The file landed on the `local` disk; `MEDIA_DISK` must be `public` |
| Deep zoom stuck on "Waiting" forever | No queue worker running |
| Deep zoom says "Failed" | Hover the status for the reason; usually vips is not installed |
| Upload fails on a large file | `upload_max_filesize` / `post_max_size` below 16M |
| Reservation emails never arrive | No queue worker, or `MAIL_MAILER` is still `log` |
| Changes to `.env` do nothing | `php artisan config:cache` needs re-running |
| New code behaves like old code | `php artisan queue:restart` |
| "Database is locked" | The `database/` directory is not writable |
| A photo 403s in the browser but works in curl | The browser cached the earlier failure; hard reload |

Logs are in `storage/logs/laravel.log`.

---

## Reference

### Public endpoints

All unauthenticated GETs except the last.

| Endpoint | Returns |
|---|---|
| `GET /api/site` | Name, tagline, contact details, socials, byline |
| `GET /api/pages/{key}` | Headings and section copy for one route |
| `GET /api/about` | The whole About page |
| `GET /api/hero-slides` | Home page carousel |
| `GET /api/categories?type=work\|post` | Gallery filters or blog categories |
| `GET /api/photos?category={slug}` | The gallery, optionally filtered |
| `GET /api/posts` · `GET /api/posts/{slug}` | Blog index and one article |
| `GET /api/workshops` · `GET /api/workshops/{slug}` | Schedule and detail |
| `GET /api/reservation-questions` | The intake form definition |
| `POST /api/workshops/{slug}/reservations` | Book a seat — throttled 5/min, 20/day per IP |

Locale resolves from `?locale=` first, then `Accept-Language`, falling back to
English.

CORS is restricted to `FRONTEND_ORIGINS` rather than `*`, because the reservation
endpoint accepts writes without authentication.

### Commands

```sh
php artisan admin:grant <email>            # grant dashboard access
php artisan admin:grant <email> --revoke   # remove it (refuses the last admin)
php artisan make:filament-user             # create an account
php artisan queue:work                     # process background jobs
php artisan queue:restart                  # after deploying new code
php artisan migrate --force                # apply schema changes
php artisan test                           # 42 tests
```

### Settings worth knowing

Everything is in `.env`; `.env.example` documents each one at the point of use.

| Setting | Purpose |
|---|---|
| `APP_URL` | The API's public address. Image URLs are built from it |
| `FRONTEND_ORIGINS` | Which sites may call the API (CORS) |
| `MEDIA_DISK` | Must be `public` |
| `DB_CONNECTION` | `sqlite` or `mysql` |
| `VIPS_BINARY` | Only if vips is not on `PATH` |
| `GIGAPIXEL_TILE_SIZE` | 512 by default; 256 produces ~4× more files |
| `GIGAPIXEL_DISK` | Where tiles are written |
| `GIGAPIXEL_TIMEOUT` | Longest a tiling job may run |

### Who can reach the dashboard

`users.is_admin` gates it, enforced by `canAccessPanel()` on the `User` model. A
row in `users` is **not** enough. Without the flag, login is rejected with the
same "credentials do not match" message as a wrong password, so the panel never
reveals whether an account exists.

`is_admin` cannot be mass-assigned, and `admin:grant --revoke` refuses to remove
the last admin. `tests/Feature/AdminPanelAccessTest.php` guards this.

Two-factor authentication is available per user at `/admin/profile`, with eight
recovery codes. To make it mandatory, set `isRequired: true` in
`AdminPanelProvider`.

### Mail

`MAIL_MAILER=log` writes mail to `storage/logs/laravel.log` instead of sending
it. Set real SMTP credentials and `MAIL_ADMIN_ADDRESS` — who receives the
internal "new reservation" notification — before relying on it.

### Not built

The shop. Everything else the public site renders is served from this API and
editable in the dashboard.
