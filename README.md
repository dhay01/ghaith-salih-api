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

Photos/galleries, blog posts, and the shop. The gigapixel deep-zoom the
frontend stubs out needs `vips dzsave` in a queued job — install `vips` first.
