# Breakster

Breakster is a real-time multiplayer music party game. Players listen to short preview clips and try to place each track in the correct chronological position on their personal timeline. Points are awarded based on placement accuracy. The game also features an AMEN mechanic — a race to claim a bonus round where you guess the artist and title, with other players voting on your answer.

## Legal constraint

- Breakster never streams full copyrighted audio.
- It only plays preview clips via HTML5 `<audio>` when a `preview_url` is present.
- Otherwise it shows an external deep link ("Play in TIDAL" / "Play in Spotify") via `external_url`.

## Tech stack

- **Backend:** Laravel 12, PHP 8.4
- **Admin panel:** Filament 5
- **Frontend:** Vue 3 + Inertia.js, Tailwind CSS 4
- **Realtime:** Laravel Reverb (WebSockets) via Laravel Echo
- **Music providers:** TIDAL, Spotify
- **Testing:** Pest 4 (Feature + Unit)
- **Frontend tooling:** Vite+ (`vp`) with Bun package manager
- **PWA:** `vite-plugin-pwa` + service worker
- **Local infra:** DDEV (Docker) + PostgreSQL

## Local development (DDEV + Postgres)

### 1) Start containers

```bash
ddev start
```

### 2) Install dependencies

```bash
ddev composer install
vp install
```

### 3) Configure env

```bash
cp .env.example .env
ddev php artisan key:generate
```

### 4) Migrate + seed

```bash
ddev php artisan migrate --seed
```

### 5) Start dev servers

In separate terminals:

```bash
ddev php artisan reverb:start   # WebSocket server
vp dev                          # Frontend dev server with HMR
ddev php artisan queue:work     # Queue worker
```

Or use the single-command shortcut (without DDEV):

```bash
composer run dev
```

This starts the web server, queue worker, log tail, and Vite concurrently.

### Frontend commands (Vite+)

```bash
vp dev      # Start frontend dev server
vp build    # Production build
vp check    # Format + lint + type checks
vp test     # Frontend tests
```

### URLs

- App: `https://breakster.ddev.site/`
- Admin (Filament): `https://breakster.ddev.site/admin`

### Seeded admin user

- Email: `admin@breakster.test`
- Password: `password`

## Real-time updates (Laravel Reverb)

Breakster uses Laravel Reverb for WebSocket-based real-time updates across all clients in a room.

### How it works

- **Events:** Game state changes (player joins, round starts, placement submitted, reveal, AMEN claims) broadcast via Reverb
- **Channels:** Each room has a scoped channel (`room.{roomCode}`) to prevent cross-room data leaks
- **Security:** Events contain minimal payloads (IDs only, no track metadata pre-reveal)
- **Client:** Vue components receive events via Laravel Echo listeners in `resources/js/room-echo.js`

### Environment variables

Required in `.env` (pre-configured in `.env.example` for DDEV):

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=breakster.ddev.site
REVERB_PORT=8080
REVERB_SCHEME=https
```

## Music providers

### TIDAL

Required env vars:

```dotenv
TIDAL_CLIENT_ID=
TIDAL_CLIENT_SECRET=
TIDAL_REDIRECT_URI=https://breakster.ddev.site/auth/tidal/callback
TIDAL_COUNTRY_CODE=DE
```

Set `TIDAL_MOCK=true` to use mock data without real TIDAL API calls.

**Admin connect flow:**

1. Open Filament: `https://breakster.ddev.site/admin`
2. Open a Pack and click "Connect TIDAL"
3. Complete the OAuth flow

**Import examples:**

- Track: `https://tidal.com/browse/track/<id>` or `track:<id>`
- Playlist: `https://tidal.com/browse/playlist/<id>` or `playlist:<id>`

### Spotify

Required env vars:

```dotenv
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
SPOTIFY_REDIRECT_URI=https://breakster.ddev.site/auth/spotify/callback
SPOTIFY_MARKET=DE
```

Players connect Spotify from within a room to enable device selection and playback control.

## Game configuration

Placement timing is configurable via environment variables:

```dotenv
GAME_PLACEMENT_DELAY=3      # Seconds before placement unlocks after round starts
GAME_PLACEMENT_DURATION=120  # Seconds for the placement window
```

Hosts can also toggle the placement timer per room.

## Tests

```bash
ddev php artisan test                          # All tests
ddev php artisan test tests/Feature/SomeTest.php  # Single file
ddev php artisan test --filter=testName        # Single test
vp check                                       # Frontend format/lint/type-check
```

## License

This project is licensed under **GNU GPL v3.0 or later** (`GPL-3.0-or-later`).
