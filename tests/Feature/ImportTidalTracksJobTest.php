<?php

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

describe('ImportTidalTracksJob', function () {
    beforeEach(function () {
        config()->set('services.tidal.client_id', 'test-client-id');
        config()->set('services.tidal.client_secret', 'test-client-secret');
        config()->set('services.tidal.redirect_uri', 'https://breakster.ddev.site/auth/tidal/callback');
        config()->set('services.tidal.country_code', 'DE');

        $this->provider = Provider::factory()->create([
            'key' => 'tidal',
            'name' => 'TIDAL',
        ]);

        $this->pack = Pack::factory()->create([
            'name' => 'Test Pack',
        ]);
    });

    it('imports tracks from TIDAL source', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                        'imageUrl' => 'https://example.com/artwork.jpg',
                    ],
                    'relationships' => [
                        'artists' => ['data' => [['type' => 'artist', 'id' => 'artist-1']]],
                        'album' => ['data' => ['type' => 'album', 'id' => 'album-1']],
                    ],
                ],
                'included' => [
                    ['type' => 'artist', 'id' => 'artist-1', 'attributes' => ['name' => 'Test Artist']],
                    ['type' => 'album', 'id' => 'album-1', 'attributes' => ['label' => 'Test Label', 'releaseDate' => '2024-01-15']],
                ],
            ], 200),
        ]);

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        expect(Track::where('pack_id', $this->pack->id)->count())->toBe(1);

        $track = Track::where('pack_id', $this->pack->id)->first();
        expect($track)->not->toBeNull();
        expect($track->provider_id)->toBe($this->provider->id);
        expect($track->title)->toBe('Test Track');
        expect($track->artist)->toBe('Test Artist');
        expect($track->label)->toBe('Test Label');
    });

    it('updates pack tidal_source field', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        $source = 'https://tidal.com/browse/track/123';

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: $source,
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        $this->pack->refresh();
        expect($this->pack->tidal_source)->toBe($source);
    });

    it('updates existing tracks instead of creating duplicates', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        // First import
        $job1 = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );
        $job1->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        $initialCount = Track::where('pack_id', $this->pack->id)->count();

        // Second import of same source
        $job2 = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );
        $job2->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        $finalCount = Track::where('pack_id', $this->pack->id)->count();

        expect($finalCount)->toBe($initialCount);
    });

    it('creates provider if it does not exist', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        Provider::where('key', 'tidal')->delete();

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        expect(Provider::where('key', 'tidal')->exists())->toBeTrue();
        expect(Provider::where('key', 'tidal')->first()->name)->toBe('TIDAL');
    });

    it('marks imported tracks as active', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        $tracks = Track::where('pack_id', $this->pack->id)->get();

        foreach ($tracks as $track) {
            expect($track->is_active)->toBeTrue();
        }
    });

    it('can be dispatched to queue', function () {
        Queue::fake();

        ImportTidalTracksJob::dispatch($this->pack->id, 'https://tidal.com/browse/track/123');

        Queue::assertPushed(ImportTidalTracksJob::class, function ($job) {
            return $job->packId === $this->pack->id
                && $job->source === 'https://tidal.com/browse/track/123';
        });
    });

    it('stores all required track metadata', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response([
                'data' => [
                    'id' => '123',
                    'type' => 'track',
                    'attributes' => [
                        'title' => 'Test Track',
                        'releaseDate' => '2024-01-15',
                        'imageUrl' => 'https://example.com/artwork.jpg',
                        'previewUrl' => 'https://example.com/preview.mp3',
                    ],
                    'relationships' => [],
                ],
            ], 200),
        ]);

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/track/123',
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );

        $track = Track::where('pack_id', $this->pack->id)->first();

        expect($track->provider_track_id)->not->toBeNull();
        expect($track->title)->not->toBeNull();
        expect($track->artist)->not->toBeNull();
        expect($track->release_date)->not->toBeNull();
        expect($track->external_url)->not->toBeNull();
    });

    it('requires admin connection for playlist imports', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'cc-token',
                'expires_in' => 3600,
            ], 200),
            'openapi.tidal.com/*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $job = new ImportTidalTracksJob(
            packId: $this->pack->id,
            source: 'https://tidal.com/browse/playlist/playlist-1',
        );

        $job->handle(
            app(TidalUrlParser::class),
            app(TidalApiClient::class),
            app(TidalClientCredentialsTokenService::class),
            app(TidalAdminTokenService::class),
        );
    })->throws(DomainException::class, 'Connect TIDAL to import playlists');
});
