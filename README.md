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

## Real-time Updates (Laravel Reverb)

Breakster uses Laravel Reverb for real-time WebSocket updates instead of polling. This provides instant synchronization across all clients in a room.

### Starting Reverb Server (Required for Development)

In a separate terminal, run:

```bash
ddev php artisan reverb:start
```

Or run it in the background:

```bash
ddev php artisan reverb:start &
```

### How it Works

- **Events**: Game state changes (player joins, round starts, placement submitted, reveal) broadcast events via Reverb
- **Channels**: Each room has a scoped channel (`room.{roomCode}`) to prevent cross-room data leaks
- **Security**: Events contain minimal payloads (IDs only, no track metadata pre-reveal)
- **Client**: JavaScript Echo listeners in `resources/js/room-echo.js` receive events and trigger Livewire component refreshes

### Frontend Assets

After any changes to JavaScript:

```bash
ddev npm run build
# or for development with hot reload:
ddev npm run dev
```

### Broadcasting Configuration

The app uses:
- **Broadcast driver**: `reverb` (configured in `.env`)
- **Channel auth**: Public channels for MVP (scoped by room code)
- **TODO**: Upgrade to private channels with session-based player authentication for production

### Environment Variables

Required in `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=breakster.ddev.site
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Testing Real-time Updates

1. Start Reverb: `ddev php artisan reverb:start`
2. Open room in Browser A and Browser B
3. Join from Browser B → Browser A lobby updates instantly
4. Start round from host → Both browsers transition instantly
5. Submit placement → Host sees update instantly
6. Reveal → Both see results instantly

TIDAL env vars

The MVP import pipeline supports a mocked client so you can test the end-to-end flow (admin action -> queued job -> DB upserts) without credentials.

```dotenv
TIDAL_MOCK=true
TIDAL_CLIENT_ID=
TIDAL_CLIENT_SECRET=
```
