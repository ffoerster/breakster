<?php

declare(strict_types=1);

namespace App\Domain\Providers\Tidal;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TidalClient
{
    /**
     * @return array{type: 'album'|'playlist'|'track', id: string}
     */
    public function parseSource(string $source): array
    {
        $source = trim($source);

        if ($source === '') {
            throw new \InvalidArgumentException('TIDAL source is required.');
        }

        if (Str::startsWith($source, ['http://', 'https://'])) {
            $path = parse_url($source, PHP_URL_PATH);
            $path = is_string($path) ? trim($path, '/') : '';

            if ($path === '') {
                throw new \InvalidArgumentException('Could not parse TIDAL URL.');
            }

            // Examples we accept:
            // - tidal.com/browse/album/123
            // - tidal.com/browse/playlist/<uuid>
            // - tidal.com/browse/track/123
            if (preg_match('~/(album|playlist|track)/([^/]+)~', "/{$path}", $matches) !== 1) {
                throw new \InvalidArgumentException('Unsupported TIDAL URL format.');
            }

            /** @var 'album'|'playlist'|'track' $type */
            $type = $matches[1];
            $id = $matches[2];

            return ['type' => $type, 'id' => $id];
        }

        // If a bare ID is provided we treat it as a playlist by default (common for UUIDs).
        // Numeric-only IDs are treated as an album (common for albums).
        if (preg_match('/^\d+$/', $source) === 1) {
            return ['type' => 'album', 'id' => $source];
        }

        return ['type' => 'playlist', 'id' => $source];
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

        throw new \RuntimeException('Real TIDAL API integration is not configured. Set TIDAL_MOCK=true for the MVP import pipeline.');
    }

    /**
     * @return array<int, array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
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
            $trackId = (string) (100000 + mt_rand(0, 899999));
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
                'release_date' => $releaseDate,
                'artwork_url' => null,
                'preview_url' => null,
                'external_url' => "https://tidal.com/browse/track/{$trackId}",
            ];
        }

        return $tracks;
    }
}
