<?php

declare(strict_types=1);

namespace App\Domain\Providers\Tidal;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TidalApiClient
{
    private const BASE_URL = 'https://openapi.tidal.com/v2';

    public function __construct(
        private readonly string $countryCode,
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
            'countryCode' => $this->countryCode,
            'include' => 'albums,artists',
        ]);

        if (! $response->successful()) {
            $this->debugLogUnsuccessfulResponse($response, "tracks/{$id}");
            throw new RuntimeException("TIDAL API request failed [{$response->status()}]: {$response->body()}");
        }

        $json = $response->json();
        $resource = $json['data'] ?? null;

        if (! is_array($resource)) {
            throw new RuntimeException('Unexpected TIDAL response shape.');
        }

        $includedIndex = $this->buildIncludedIndex($json['included'] ?? []);

        return $this->normalizeTrackResource($resource, $includedIndex);
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
        $response = $this->get("playlists/{$playlistId}/relationships/items", $accessToken, [
            'countryCode' => $this->countryCode,
            'include' => 'track,track.artists,track.album',
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            $this->debugLogUnsuccessfulResponse($response, "playlists/{$playlistId}/relationships/items");
            throw new TidalPlaylistRequiresAdminTokenException('TIDAL playlist access requires a user token.');
        }

        if (! $response->successful()) {
            $this->debugLogUnsuccessfulResponse($response, "playlists/{$playlistId}/relationships/items");
            throw new RuntimeException("TIDAL API request failed [{$response->status()}]: {$response->body()}");
        }

        $json = $response->json();
        $includedIndex = $this->buildIncludedIndex($json['included'] ?? []);

        $tracks = [];
        foreach (($json['data'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'track' && isset($item['id'])) {
                $trackResource = $includedIndex["track:{$item['id']}"] ?? null;

                if (is_array($trackResource)) {
                    $tracks[] = $this->normalizeTrackResource($trackResource, $includedIndex);

                    continue;
                }

                $tracks[] = $this->getTrack((string) $item['id'], $accessToken);
            }

            if (isset($item['relationships']['track']['data']['id'])) {
                $trackId = (string) $item['relationships']['track']['data']['id'];
                $trackResource = $includedIndex["track:{$trackId}"] ?? null;

                if (is_array($trackResource)) {
                    $tracks[] = $this->normalizeTrackResource($trackResource, $includedIndex);

                    continue;
                }

                $tracks[] = $this->getTrack($trackId, $accessToken);
            }
        }

        return array_values(array_filter($tracks, fn (array $track): bool => ($track['provider_track_id'] ?? '') !== ''));
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, array<string, mixed>>  $includedIndex
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
    private function normalizeTrackResource(array $resource, array $includedIndex): array
    {
        $id = (string) ($resource['id'] ?? '');
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];

        $title = (string) ($attributes['title'] ?? '');
        $releaseDate = $this->normalizeDate(
            $attributes['releaseDate']
                ?? $attributes['release_date']
                ?? $attributes['releaseDateTime']
                ?? null,
        );

        $artist = $this->resolveArtistName($resource, $includedIndex) ?? 'Unknown Artist';

        $albumAttributes = $this->resolveAlbumAttributes($resource, $includedIndex);
        $label = is_array($albumAttributes) ? ($albumAttributes['label'] ?? null) : null;

        if ($releaseDate === null && is_array($albumAttributes)) {
            $releaseDate = $this->normalizeDate($albumAttributes['releaseDate'] ?? $albumAttributes['release_date'] ?? null);
        }

        $artworkUrl = $this->extractArtworkUrl($attributes);
        if ($artworkUrl === null && is_array($albumAttributes)) {
            $artworkUrl = $this->extractArtworkUrl($albumAttributes);
        }

        $previewUrl = $this->extractPreviewUrl($attributes);

        return [
            'provider_track_id' => $id,
            'title' => $title !== '' ? $title : 'Unknown Title',
            'artist' => $artist,
            'label' => is_string($label) && $label !== '' ? $label : null,
            'release_date' => $releaseDate ?? now()->format('Y-m-d'),
            'artwork_url' => $artworkUrl,
            'preview_url' => $previewUrl,
            'external_url' => "https://tidal.com/browse/track/{$id}",
        ];
    }

    private function resolveArtistName(array $resource, array $includedIndex): ?string
    {
        $artists = $resource['relationships']['artists']['data'] ?? null;
        if (is_array($artists) && isset($artists[0]['type'], $artists[0]['id'])) {
            $type = (string) $artists[0]['type'];
            $id = (string) $artists[0]['id'];
            $artistResource = $includedIndex["{$type}:{$id}"] ?? null;

            if (is_array($artistResource)) {
                $attrs = is_array($artistResource['attributes'] ?? null) ? $artistResource['attributes'] : [];
                $name = $attrs['name'] ?? null;

                return is_string($name) && $name !== '' ? $name : null;
            }
        }

        $artist = $resource['relationships']['artist']['data'] ?? null;
        if (is_array($artist) && isset($artist['type'], $artist['id'])) {
            $type = (string) $artist['type'];
            $id = (string) $artist['id'];
            $artistResource = $includedIndex["{$type}:{$id}"] ?? null;

            if (is_array($artistResource)) {
                $attrs = is_array($artistResource['attributes'] ?? null) ? $artistResource['attributes'] : [];
                $name = $attrs['name'] ?? null;

                return is_string($name) && $name !== '' ? $name : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAlbumAttributes(array $resource, array $includedIndex): ?array
    {
        $album = $resource['relationships']['album']['data'] ?? null;

        if (! is_array($album) || ! isset($album['type'], $album['id'])) {
            $albums = $resource['relationships']['albums']['data'] ?? null;

            if (! is_array($albums) || ! isset($albums[0]['type'], $albums[0]['id'])) {
                return null;
            }

            $album = $albums[0];
        }

        $albumResource = $includedIndex[(string) $album['type'].':'.(string) $album['id']] ?? null;

        if (! is_array($albumResource)) {
            return null;
        }

        return is_array($albumResource['attributes'] ?? null) ? $albumResource['attributes'] : null;
    }

    /**
     * @param  array<int, mixed>  $included
     * @return array<string, array<string, mixed>>
     */
    private function buildIncludedIndex(array $included): array
    {
        $index = [];

        foreach ($included as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            if (! isset($resource['type'], $resource['id'])) {
                continue;
            }

            $key = (string) $resource['type'].':'.(string) $resource['id'];
            $index[$key] = $resource;
        }

        return $index;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) >= 10) {
            return substr($value, 0, 10);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function extractArtworkUrl(array $attributes): ?string
    {
        foreach (['artworkUrl', 'artwork_url', 'imageUrl', 'image_url', 'image', 'coverUrl', 'cover_url'] as $key) {
            $value = $attributes[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $imageLinks = $attributes['imageLinks'] ?? $attributes['image_links'] ?? null;
        if (is_array($imageLinks)) {
            $candidates = [];
            foreach ($imageLinks as $link) {
                if (is_string($link) && $link !== '') {
                    $candidates[] = $link;
                }

                if (is_array($link) && isset($link['href']) && is_string($link['href']) && $link['href'] !== '') {
                    $candidates[] = $link['href'];
                }
            }

            return $candidates !== [] ? $candidates[array_key_last($candidates)] : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function extractPreviewUrl(array $attributes): ?string
    {
        foreach (['previewUrl', 'preview_url', 'preview', 'previewMp3Url', 'preview_mp3_url'] as $key) {
            $value = $attributes[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $endpoint, string $accessToken, array $query = []): Response
    {
        /** @var Response $response */
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ])->get(self::BASE_URL.'/'.ltrim($endpoint, '/'), $query);

        return $response;
    }

    private function debugLogUnsuccessfulResponse(Response $response, string $endpoint): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $body = $response->body();

        Log::debug('TIDAL API non-2xx response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => substr($body, 0, 500),
        ]);
    }
}
