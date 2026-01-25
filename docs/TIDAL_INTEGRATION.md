# TIDAL Web API Integration

This document describes the TIDAL Web API integration for Breakster, which allows administrators to import tracks from TIDAL playlists, albums, and individual tracks.

## Overview

The TIDAL integration supports:
- Importing tracks from TIDAL URLs (playlists, albums, tracks)
- OAuth 2.1 Client Credentials flow for API authentication
- Automatic token caching to minimize API calls
- Queued imports for better performance
- Mock mode for development/testing without real API credentials

## Architecture

### Components

#### 1. TidalTokenService
**Location:** `app/Domain/Providers/Tidal/TidalTokenService.php`

Handles OAuth 2.1 Client Credentials authentication with TIDAL's API.

**Features:**
- Fetches access tokens from TIDAL's token endpoint
- Caches tokens until expiry (with 60-second safety buffer)
- Automatically refreshes expired tokens
- Thread-safe token management

**Usage:**
```php
$tokenService = app(TidalTokenService::class);
$token = $tokenService->getAccessToken();
```

#### 2. TidalUrlParser
**Location:** `app/Domain/Providers/Tidal/TidalUrlParser.php`

Parses TIDAL URLs and IDs to extract resource type and ID.

**Supported Formats:**
- `https://tidal.com/browse/track/123456`
- `https://tidal.com/browse/album/789012`
- `https://tidal.com/browse/playlist/uuid-1234-5678`
- `https://listen.tidal.com/track/123456`
- `123456` (numeric ID defaults to track)

**Usage:**
```php
$parser = app(TidalUrlParser::class);
$result = $parser->parse('https://tidal.com/browse/track/123456');
// Returns: ['type' => 'track', 'id' => '123456']
```

#### 3. TidalClient
**Location:** `app/Domain/Providers/Tidal/TidalClient.php`

Main client for interacting with TIDAL's API.

**Features:**
- Fetches track metadata from TIDAL API
- Supports tracks, albums, and playlists
- Normalizes API responses to consistent format
- Mock mode for development/testing

**Track Metadata:**
- `provider_track_id`: TIDAL track ID
- `title`: Track title
- `artist`: Artist name
- `label`: Record label (if available)
- `release_date`: Release date
- `artwork_url`: Album artwork URL
- `preview_url`: Preview URL (if available)
- `external_url`: Link to track on TIDAL

**Usage:**
```php
$client = app(TidalClient::class);
$tracks = $client->fetchTracks('https://tidal.com/browse/album/123456');
```

#### 4. ImportTidalTracksJob
**Location:** `app/Jobs/ImportTidalTracksJob.php`

Queued job that imports tracks from TIDAL into a Pack.

**Features:**
- Runs in background queue
- Upserts tracks (creates new, updates existing)
- Prevents duplicates
- Updates Pack's `tidal_source` field
- Logs import statistics

**Usage:**
```php
ImportTidalTracksJob::dispatch($packId, 'https://tidal.com/browse/album/123456');
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# TIDAL API Configuration
TIDAL_MOCK=true                  # Set to false to use real API
TIDAL_CLIENT_ID=your-client-id
TIDAL_CLIENT_SECRET=your-secret
```

### Mock Mode

Mock mode (`TIDAL_MOCK=true`) generates fake track data for development and testing without requiring real TIDAL API credentials. This is useful for:
- Local development
- Automated testing
- Demo/staging environments

To use real TIDAL API:
1. Set `TIDAL_MOCK=false`
2. Obtain API credentials from [TIDAL Developer Portal](https://developer.tidal.com/)
3. Set `TIDAL_CLIENT_ID` and `TIDAL_CLIENT_SECRET`

## Usage

### Admin Panel (Filament)

1. Navigate to Packs in the admin panel
2. Edit a Pack
3. Click "Import from TIDAL" button
4. Paste a TIDAL URL or ID
5. Click import - job will be queued
6. Tracks will be imported in the background

### Programmatic Import

```php
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;

$pack = Pack::find(1);
ImportTidalTracksJob::dispatch(
    $pack->id,
    'https://tidal.com/browse/album/123456'
);
```

### Direct Client Usage

```php
use App\Domain\Providers\Tidal\TidalClient;

$client = app(TidalClient::class);
$tracks = $client->fetchTracks('https://tidal.com/browse/track/123456');

foreach ($tracks as $track) {
    echo "{$track['title']} by {$track['artist']}\n";
}
```

## Database Schema

### providers Table
```sql
CREATE TABLE providers (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR UNIQUE NOT NULL,
    name VARCHAR NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### tracks Table Updates
The tracks table now uses a foreign key to the providers table:

```sql
ALTER TABLE tracks
ADD COLUMN provider_id BIGINT REFERENCES providers(id) ON DELETE CASCADE,
ADD COLUMN label VARCHAR,
ADD UNIQUE (pack_id, provider_id, provider_track_id);
```

## Testing

### Run All TIDAL Tests
```bash
ddev exec "php artisan test --filter=Tidal"
```

### Run Specific Test Suites
```bash
# URL Parser tests
ddev exec "php artisan test --filter=TidalUrl"

# Token Service tests
ddev exec "php artisan test --filter=TidalToken"

# Client tests
ddev exec "php artisan test --filter=TidalClient"

# Import Job tests
ddev exec "php artisan test --filter=ImportTidal"
```

### Test Coverage
- **TidalUrlParser**: URL parsing, error handling
- **TidalTokenService**: Token fetching, caching, refresh
- **TidalClient**: API calls, data normalization
- **ImportTidalTracksJob**: Import logic, deduplication, queue integration

## API Rate Limits

TIDAL's API has rate limits. The integration handles this through:
- Token caching (reduces auth requests)
- Queued imports (prevents concurrent API abuse)
- Mock mode for development (no API calls)

## Security Considerations

### No Full-Track Streaming
The integration **never** stores or provides full copyrighted audio streams. It only:
- Stores metadata (title, artist, etc.)
- Stores preview URLs when available from TIDAL
- Stores external URLs linking back to TIDAL

### Preview URLs
TIDAL's catalog API typically doesn't provide preview URLs in standard responses. Preview functionality may require additional authentication or subscription levels.

## Troubleshooting

### Migration Issues
If migrations fail, ensure providers table is created before tracks table:
```bash
ddev php artisan migrate:fresh --seed
```

### Token Errors
If you get authentication errors:
1. Verify `TIDAL_CLIENT_ID` and `TIDAL_CLIENT_SECRET`
2. Check token endpoint is accessible
3. Clear cache: `ddev php artisan cache:clear`

### Import Failures
Check logs for import job failures:
```bash
ddev php artisan queue:failed
```

Retry failed jobs:
```bash
ddev php artisan queue:retry all
```

## Future Enhancements

Potential improvements:
- Authorization Code (PKCE) flow for user-specific playlists
- Incremental updates (sync changes from TIDAL)
- Webhook support for automatic updates
- Advanced error handling and retry logic
- Import progress tracking
- Batch import optimization

## Resources

- [TIDAL Developer Portal](https://developer.tidal.com/)
- [TIDAL API Documentation](https://developer.tidal.com/documentation/api)
- [OAuth 2.1 Specification](https://oauth.net/2.1/)
