#+#+#+#+--------------------------------------------------------------------
Breakster
------------------------------------------------------------------------

Breakster is a timeline-based music party game. Players listen to a short preview (when available) and try to place the track in the correct chronological position in their personal timeline.

Legal constraint (MVP)
- Breakster never streams full copyrighted audio.
- It only plays preview clips via HTML5 `<audio>` when a `preview_url` is present.
- Otherwise it shows an external deep link ("Play in TIDAL") via `external_url`.

Local development (DDEV + Postgres)

1) Start containers

```bash
ddev start
```

2) Install PHP deps

```bash
ddev composer install
```

3) Configure env

```bash
cp .env.example .env
ddev php artisan key:generate
```

4) Migrate + seed

```bash
ddev php artisan migrate --seed
```

URLs
- App: `https://breakster.ddev.site/`
- Admin (Filament): `https://breakster.ddev.site/admin`

Seeded admin user
- Email: `admin@breakster.test`
- Password: `password`

Queue worker (database queue)

```bash
ddev php artisan queue:work
```

Tests

```bash
ddev php artisan test
```

TIDAL env vars

The MVP import pipeline supports a mocked client so you can test the end-to-end flow (admin action -> queued job -> DB upserts) without credentials.

```dotenv
TIDAL_MOCK=true
TIDAL_CLIENT_ID=
TIDAL_CLIENT_SECRET=
```
