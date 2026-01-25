<?php

use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalPlaylistRequiresAdminTokenException;
use Illuminate\Support\Facades\Http;

describe('TidalApiClient', function () {
    beforeEach(function () {
        $this->client = new TidalApiClient(countryCode: 'DE');
    });

    it('fetches single track metadata', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123456',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                        'imageUrl' => 'https://example.com/artwork.jpg',
                    ],
                    'relationships' => [
                        'artists' => [
                            'data' => [
                                [
                                    'type' => 'artist',
                                    'id' => 'artist-1',
                                ],
                            ],
                        ],
                        'album' => [
                            'data' => [
                                'type' => 'album',
                                'id' => 'album-1',
                            ],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'artist',
                        'id' => 'artist-1',
                        'attributes' => [
                            'name' => 'Test Artist',
                        ],
                    ],
                    [
                        'type' => 'album',
                        'id' => 'album-1',
                        'attributes' => [
                            'label' => 'Test Label',
                            'releaseDate' => '2024-01-15',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $track = $this->client->getTrack('123456', 'test-token-123');

        expect($track)->toMatchArray([
            'provider_track_id' => '123456',
            'title' => 'Test Track',
            'artist' => 'Test Artist',
            'label' => 'Test Label',
            'release_date' => '2024-01-15',
            'artwork_url' => 'https://example.com/artwork.jpg',
            'external_url' => 'https://tidal.com/browse/track/123456',
        ]);
    });

    it('uses correct API headers', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123456',
                    'type' => 'track',
                    'attributes' => ['title' => 'Test'],
                    'relationships' => [
                        'artists' => ['data' => [['type' => 'artist', 'id' => 'artist-1']]],
                        'album' => ['data' => ['type' => 'album', 'id' => 'album-1']],
                    ],
                ],
                'included' => [
                    ['type' => 'artist', 'id' => 'artist-1', 'attributes' => ['name' => 'Artist']],
                    ['type' => 'album', 'id' => 'album-1', 'attributes' => ['releaseDate' => '2024-01-01']],
                ],
            ], 200),
        ]);

        $this->client->getTrack('123456', 'test-token-123');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'openapi.tidal.com/v2/')
                && $request->hasHeader('Authorization', 'Bearer test-token-123')
                && $request->hasHeader('Accept', 'application/vnd.tidal.v1+json')
                && $request->hasHeader('Content-Type', 'application/vnd.tidal.v1+json');
        });
    });

    it('handles missing optional fields gracefully', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123456',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Minimal Track',
                        'releaseDate' => '2024-01-01',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        $track = $this->client->getTrack('123456', 'test-token-123');

        expect($track)->toMatchArray([
            'provider_track_id' => '123456',
            'title' => 'Minimal Track',
            'artist' => 'Unknown Artist',
            'label' => null,
            'artwork_url' => null,
            'preview_url' => null,
            'release_date' => '2024-01-01',
        ]);
    });

    it('throws exception on API error', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response([
                'error' => 'not_found',
                'message' => 'Track not found',
            ], 404),
        ]);

        $this->client->getTrack('999999', 'test-token-123');
    })->throws(RuntimeException::class, 'TIDAL API request failed');

    it('fetches playlist tracks', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    ['type' => 'track', 'id' => 't-1'],
                    ['type' => 'track', 'id' => 't-2'],
                ],
                'included' => [
                    [
                        'type' => 'track',
                        'id' => 't-1',
                        'attributes' => ['title' => 'Track 1', 'releaseDate' => '2020-01-01'],
                        'relationships' => ['artists' => ['data' => [['type' => 'artist', 'id' => 'a-1']]]],
                    ],
                    [
                        'type' => 'track',
                        'id' => 't-2',
                        'attributes' => ['title' => 'Track 2', 'releaseDate' => '2020-02-01'],
                        'relationships' => ['artists' => ['data' => [['type' => 'artist', 'id' => 'a-1']]]],
                    ],
                    ['type' => 'artist', 'id' => 'a-1', 'attributes' => ['name' => 'Artist']],
                ],
            ], 200),
        ]);

        $tracks = $this->client->getPlaylistTracks('p-1', 'test-token-123');

        expect($tracks)->toHaveCount(2);
        expect($tracks[0]['provider_track_id'])->toBe('t-1');
        expect($tracks[1]['provider_track_id'])->toBe('t-2');
    });

    it('requires admin token for playlist when unauthorized', function () {
        Http::fake([
            'openapi.tidal.com/*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $this->client->getPlaylistTracks('p-1', 'test-token-123');
    })->throws(TidalPlaylistRequiresAdminTokenException::class);
});
