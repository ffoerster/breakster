<?php

declare(strict_types=1);

namespace App\Domain\Providers\Spotify;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SpotifyApiClient
{
    private const BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(
        private readonly string $market = 'DE',
    ) {}

    /**
     * @return array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string
     * }
     */
    public function getTrack(string $id, string $accessToken): array
    {
        $response = $this->get("tracks/{$id}", $accessToken, [
            'market' => $this->market,
        ]);

        if (!$response->successful()) {
            $this->handleErrorResponse($response, "tracks/{$id}");
        }

        $track = $response->json();

        if (!is_array($track) || !isset($track['id'])) {
            throw new RuntimeException('Unexpected Spotify response shape.');
        }

        return $this->normalizeTrack($track, $accessToken);
    }

    /**
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string
     * }>
     */
    public function getPlaylistTracks(string $playlistId, string $accessToken): array
    {
        $tracks = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->get("playlists/{$playlistId}/tracks", $accessToken, [
                'market' => $this->market,
                'fields' => 'items(track(id,name,artists,album,preview_url,external_urls)),next,total',
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw new SpotifyPlaylistRequiresAdminTokenException(
                    'Spotify playlist access requires user authorization.',
                );
            }

            if (!$response->successful()) {
                $this->handleErrorResponse($response, "playlists/{$playlistId}/tracks");
            }

            $data = $response->json();

            foreach ($data['items'] ?? [] as $item) {
                $track = $item['track'] ?? null;

                if (!is_array($track) || !isset($track['id'])) {
                    continue;
                }

                // Skip local tracks (they have null id)
                if ($track['id'] === null) {
                    continue;
                }

                $tracks[] = $this->normalizeTrackFromPlaylist($track);
            }

            // Handle pagination
            $nextUrl = $data['next'] ?? null;
            $offset += $limit;
        } while ($nextUrl !== null);

        return $tracks;
    }

    /**
     * Fetch album details to get label information.
     *
     * @return array<string, mixed>|null
     */
    public function getAlbum(string $albumId, string $accessToken): ?array
    {
        $response = $this->get("albums/{$albumId}", $accessToken, [
            'market' => $this->market,
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * @return array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string
     * }
     */
    private function normalizeTrack(array $track, string $accessToken): array
    {
        $id = (string) $track['id'];
        $title = (string) ($track['name'] ?? 'Unknown Title');

        // Extract artists
        $artists = $track['artists'] ?? [];
        $artistNames = array_map(fn($a) => $a['name'] ?? '', $artists);
        $artist = implode(', ', array_filter($artistNames)) ?: 'Unknown Artist';

        // Extract album info
        $album = $track['album'] ?? [];
        $releaseDate = $this->normalizeReleaseDate(
            $album['release_date'] ?? null,
            $album['release_date_precision'] ?? null,
        );
        $artworkUrl = $this->extractArtworkUrl($album['images'] ?? []);

        // Fetch label from album (requires additional API call)
        $label = null;
        $albumId = $album['id'] ?? null;
        if (is_string($albumId) && $albumId !== '') {
            $albumDetails = $this->getAlbum($albumId, $accessToken);
            $label = $albumDetails['label'] ?? null;
        }

        // Preview URL (may be null due to regional restrictions or licensing)
        $previewUrl = is_string($track['preview_url'] ?? null) ? $track['preview_url'] : null;

        // External URL
        $externalUrl = $track['external_urls']['spotify'] ?? "https://open.spotify.com/track/{$id}";

        return [
            'provider_track_id' => $id,
            'title' => $title,
            'artist' => $artist,
            'label' => is_string($label) && $label !== '' ? $label : null,
            'release_date' => $releaseDate,
            'artwork_url' => $artworkUrl,
            'preview_url' => $previewUrl,
            'external_url' => (string) $externalUrl,
        ];
    }

    /**
     * Normalize track from playlist response (no additional album API call for performance).
     *
     * @return array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string
     * }
     */
    private function normalizeTrackFromPlaylist(array $track): array
    {
        $id = (string) $track['id'];
        $title = (string) ($track['name'] ?? 'Unknown Title');

        $artists = $track['artists'] ?? [];
        $artistNames = array_map(fn($a) => $a['name'] ?? '', $artists);
        $artist = implode(', ', array_filter($artistNames)) ?: 'Unknown Artist';

        $album = $track['album'] ?? [];
        $releaseDate = $this->normalizeReleaseDate(
            $album['release_date'] ?? null,
            $album['release_date_precision'] ?? null,
        );
        $artworkUrl = $this->extractArtworkUrl($album['images'] ?? []);

        // Skip label for playlist imports (would require N+1 album API calls)
        $previewUrl = is_string($track['preview_url'] ?? null) ? $track['preview_url'] : null;
        $externalUrl = $track['external_urls']['spotify'] ?? "https://open.spotify.com/track/{$id}";

        return [
            'provider_track_id' => $id,
            'title' => $title,
            'artist' => $artist,
            'label' => null,
            'release_date' => $releaseDate,
            'artwork_url' => $artworkUrl,
            'preview_url' => $previewUrl,
            'external_url' => (string) $externalUrl,
        ];
    }

    private function normalizeReleaseDate(?string $date, ?string $precision): string
    {
        if (!is_string($date) || $date === '') {
            return now()->format('Y-m-d');
        }

        // Spotify returns dates with varying precision: year, month, or day
        return match ($precision) {
            'day' => $date,
            'month' => "{$date}-01",
            'year' => "{$date}-01-01",
            default => strlen($date) >= 10 ? substr($date, 0, 10) : "{$date}-01-01",
        };
    }

    /**
     * @param  array<int, array{url?: string, height?: int, width?: int}>  $images
     */
    private function extractArtworkUrl(array $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        // Prefer largest image (first in array from Spotify)
        foreach ($images as $image) {
            if (isset($image['url']) && is_string($image['url'])) {
                return $image['url'];
            }
        }

        return null;
    }

    /**
     * Get available Spotify Connect devices for the user.
     *
     * @return array{devices: array<int, array{id: string, name: string, type: string, is_active: bool}>}
     */
    public function getDevices(string $accessToken): array
    {
        $response = $this->get('me/player/devices', $accessToken);

        if ($response->status() === 403) {
            throw new RuntimeException('Spotify Premium required for playback control.');
        }

        if (!$response->successful()) {
            $this->handleErrorResponse($response, 'me/player/devices');
        }

        $data = $response->json();

        return [
            'devices' => array_map(fn($device) => [
                'id' => (string) $device['id'],
                'name' => (string) $device['name'],
                'type' => (string) $device['type'],
                'is_active' => (bool) $device['is_active'],
            ], $data['devices'] ?? []),
        ];
    }

    /**
     * Transfer playback to a specific device.
     */
    public function transferPlayback(string $deviceId, string $accessToken, bool $play = false): void
    {
        $response = $this->put('me/player', $accessToken, [
            'device_ids' => [$deviceId],
            'play' => $play,
        ]);

        if ($response->status() === 403) {
            throw new RuntimeException('Spotify Premium required for playback control.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException(
                'No active Spotify device found. Open Spotify on a device and start playback once.',
            );
        }

        if (!$response->successful() && $response->status() !== 204) {
            $this->handleErrorResponse($response, 'me/player');
        }
    }

    /**
     * Start playback of a specific track on a device.
     */
    public function startPlayback(string $deviceId, string $trackUri, string $accessToken): void
    {
        $response = $this->put("me/player/play?device_id={$deviceId}", $accessToken, [
            'uris' => [$trackUri],
        ]);

        if ($response->status() === 403) {
            throw new RuntimeException('Spotify Premium required for playback control.');
        }

        if ($response->status() === 404) {
            $body = $response->json();
            $reason = $body['error']['reason'] ?? 'NO_ACTIVE_DEVICE';

            if ($reason === 'NO_ACTIVE_DEVICE') {
                throw new RuntimeException(
                    'No active Spotify device found. Open Spotify on a device and start playback once.',
                );
            }

            throw new RuntimeException('Spotify playback failed: ' . $reason);
        }

        if (!$response->successful() && $response->status() !== 204) {
            $this->handleErrorResponse($response, 'me/player/play');
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $endpoint, string $accessToken, array $query = []): Response
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/json',
        ])->get(self::BASE_URL . '/' . ltrim($endpoint, '/'), $query);

        // Handle rate limiting with Retry-After header
        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 1);
            Log::warning('Spotify API rate limited', [
                'endpoint' => $endpoint,
                'retry_after' => $retryAfter,
            ]);
            sleep(min($retryAfter, 60)); // Cap at 60 seconds

            return $this->get($endpoint, $accessToken, $query);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function put(string $endpoint, string $accessToken, array $body = []): Response
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->withBody(json_encode($body), 'application/json')->put(self::BASE_URL . '/' . ltrim($endpoint, '/'));

        // Handle rate limiting with Retry-After header
        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 1);
            Log::warning('Spotify API rate limited', [
                'endpoint' => $endpoint,
                'retry_after' => $retryAfter,
            ]);
            sleep(min($retryAfter, 60)); // Cap at 60 seconds

            return $this->put($endpoint, $accessToken, $body);
        }

        return $response;
    }

    private function handleErrorResponse(Response $response, string $endpoint): never
    {
        $this->debugLogUnsuccessfulResponse($response, $endpoint);
        throw new RuntimeException("Spotify API request failed [{$response->status()}]: {$response->body()}");
    }

    private function debugLogUnsuccessfulResponse(Response $response, string $endpoint): void
    {
        if (!app()->environment('local')) {
            return;
        }

        Log::debug('Spotify API non-2xx response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 500),
        ]);
    }
}
