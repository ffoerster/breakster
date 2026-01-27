<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Tidal;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TidalClient
{
    private const API_BASE_URL = 'https://openapi.tidal.com';

    private const API_VERSION = 'v2';

    public function __construct(
        private readonly TidalTokenService $tokenService,
        private readonly TidalUrlParser $urlParser,
    ) {}

    /**
     * @return array{type: 'album'|'playlist'|'track', id: string}
     */
    public function parseSource(string $source): array
    {
        $parsed = $this->urlParser->parse($source);

        /** @var 'album'|'playlist'|'track' $type */
        $type = $parsed['type'];

        return ['type' => $type, 'id' => $parsed['id']];
    }

    /**
     * Fetch tracks from TIDAL and normalize the response.
     *
     * IMPORTANT: This client must NEVER return full copyrighted audio streams.
     * Only return preview clips ("preview_url") when available.
     *
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }>
     */
    public function fetchTracks(string $source): array
    {
        $parsed = $this->parseSource($source);

        if ((bool) config('services.tidal.mock', true) === true) {
            return $this->fetchTracksMock($parsed['type'], $parsed['id']);
        }

        return match ($parsed['type']) {
            'track' => [$this->fetchTrack($parsed['id'])],
            'album' => $this->fetchAlbumTracks($parsed['id']),
            'playlist' => $this->fetchPlaylistTracks($parsed['id']),
        };
    }

    /**
     * @return array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }
     */
    private function fetchTrack(string $trackId): array
    {
        $response = $this->makeApiRequest("tracks/{$trackId}", [
            'countryCode' => config('services.tidal.country_code', 'US'),
        ]);

        return $this->normalizeTrack($response['data']);
    }

    /**
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }>
     */
    private function fetchAlbumTracks(string $albumId): array
    {
        // Use the relationships endpoint to get album tracks
        // TIDAL API requires countryCode parameter for most endpoints
        $response = $this->makeApiRequest("albums/{$albumId}/relationships/items", [
            'countryCode' => config('services.tidal.country_code', 'US'),
        ]);

        $tracks = [];

        // Extract track IDs from relationship data
        foreach ($response['data'] ?? [] as $item) {
            if (!(isset($item['type']) && $item['type'] === 'track' && isset($item['id']))) { continue; }

try {
                    $tracks[] = $this->fetchTrack($item['id']);
                } catch (\RuntimeException $e) {
                    // Skip tracks that can't be fetched
                    continue;
                }
        }

        return $tracks;
    }

    /**
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }>
     */
    private function fetchPlaylistTracks(string $playlistId): array
    {
        // Use the relationships endpoint to get playlist items
        try {
            $response = $this->makeApiRequest("playlists/{$playlistId}/relationships/items", [
                'countryCode' => config('services.tidal.country_code', 'US'),
            ]);

            $tracks = [];

            // Extract track IDs from relationship data
            foreach ($response['data'] ?? [] as $item) {
                if (!(isset($item['type']) && $item['type'] === 'track' && isset($item['id']))) { continue; }

try {
                        $tracks[] = $this->fetchTrack($item['id']);
                    } catch (\RuntimeException $e) {
                        // Skip tracks that can't be fetched
                        continue;
                    }
            }

            if (empty($tracks)) {
                throw new \RuntimeException(
                    'No tracks found in playlist. The playlist might be empty or contain only non-track items.',
                );
            }

            return $tracks;
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                throw new \RuntimeException(
                    "Unable to fetch playlist tracks from TIDAL. This might be because:\n"
                        ."1. The playlist is private or doesn't exist\n"
                        ."2. Playlist access requires user authentication (not supported with Client Credentials)\n"
                        ."3. You don't have permission to access this playlist\n\n"
                        .'Original error: '
                        .$e->getMessage(),
                );
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        $token = $this->tokenService->getAccessToken();

        $url = self::API_BASE_URL.'/'.self::API_VERSION.'/'.$endpoint;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.tidal.v1+json',
            'Content-Type' => 'application/vnd.tidal.v1+json',
        ])->get($url, $params);

        if (!$response->successful()) {
            throw new RuntimeException("TIDAL API request failed [{$response->status()}]: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $trackData
     * @return array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }
     */
    private function normalizeTrack(array $trackData): array
    {
        $attributes = $trackData['attributes'] ?? [];
        $id = $trackData['id'] ?? '';

        // Extract artist names from relationships
        $artistName = 'Unknown Artist';

        if (isset($trackData['relationships']['artists']['data'][0])) {
            $artistData = $trackData['relationships']['artists']['data'][0];
            $artistName = $artistData['attributes']['name'] ?? $artistName;
        }

        // Extract album info for label and release date
        $label = null;
        $releaseDate = now()->format('Y-m-d');

        if (isset($trackData['relationships']['album']['data'])) {
            $albumData = $trackData['relationships']['album']['data'];
            $label = $albumData['attributes']['label'] ?? null;

            if (isset($albumData['attributes']['releaseDate'])) {
                $releaseDate = $albumData['attributes']['releaseDate'];
            }
        }

        // Extract artwork URL
        $artworkUrl = null;

        if (isset($attributes['imageUrl'])) {
            $artworkUrl = $attributes['imageUrl'];
        } elseif (isset($attributes['image'])) {
            $artworkUrl = $attributes['image'];
        }

        // TIDAL API typically doesn't provide preview URLs in catalog endpoints
        // Preview URLs would require additional authentication/subscription
        $previewUrl = $attributes['previewUrl'] ?? null;

        return [
            'provider_track_id' => (string) $id,
            'title' => $attributes['title'] ?? 'Unknown Title',
            'artist' => $artistName,
            'label' => $label,
            'release_date' => $releaseDate,
            'artwork_url' => $artworkUrl,
            'preview_url' => $previewUrl,
            'external_url' => "https://tidal.com/browse/track/{$id}",
        ];
    }

    /**
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label?: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string|null
     * }>
     */
    private function fetchTracksMock(string $type, string $id): array
    {
        $seed = (int) sprintf('%u', crc32("{$type}:{$id}"));
        mt_srand($seed);

        $artists = [
            'Neon Atlas',
            'Midnight Circuit',
            'Paper Satellites',
            'Static Bloom',
            'Silver Arcade',
            'Bright Hours',
            'Echo Magnet',
        ];

        $tracks = [];
        $count = 25;

        for ($i = 1; $i <= $count; $i++) {
            $trackId = (string) ( 100000 + mt_rand(0, 899999) );
            $year = mt_rand(1965, 2025);
            $month = mt_rand(1, 12);
            $day = mt_rand(1, 28);

            $releaseDate = CarbonImmutable::create($year, $month, $day)->format('Y-m-d');
            $artist = $artists[array_rand($artists)];
            $title = sprintf('Track %02d', $i);

            $tracks[] = [
                'provider_track_id' => $trackId,
                'title' => $title,
                'artist' => $artist,
                'label' => null,
                'release_date' => $releaseDate,
                'artwork_url' => null,
                'preview_url' => null,
                'external_url' => "https://tidal.com/browse/track/{$trackId}",
            ];
        }

        return $tracks;
    }
}
