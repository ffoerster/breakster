<?php

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;
use App\Models\Track;
use Illuminate\Support\Facades\Http;

it('imports a TIDAL track into a pack', function () {
    config()->set('services.tidal.client_id', 'test-client-id');
    config()->set('services.tidal.client_secret', 'test-client-secret');
    config()->set('services.tidal.redirect_uri', 'https://breakster.ddev.site/auth/tidal/callback');
    config()->set('services.tidal.country_code', 'DE');

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

    $pack = Pack::factory()->create();

    $job = new ImportTidalTracksJob($pack->id, 'https://tidal.com/browse/track/123');
    $job->handle(
        app(TidalUrlParser::class),
        app(TidalApiClient::class),
        app(TidalClientCredentialsTokenService::class),
        app(TidalAdminTokenService::class),
    );

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(1);
    expect($pack->fresh()->tidal_source)->toBe('https://tidal.com/browse/track/123');
});

it('does not create duplicates when importing the same source twice', function () {
    config()->set('services.tidal.client_id', 'test-client-id');
    config()->set('services.tidal.client_secret', 'test-client-secret');
    config()->set('services.tidal.redirect_uri', 'https://breakster.ddev.site/auth/tidal/callback');
    config()->set('services.tidal.country_code', 'DE');

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

    $pack = Pack::factory()->create();

    $job = new ImportTidalTracksJob($pack->id, 'https://tidal.com/browse/track/123');
    $job->handle(
        app(TidalUrlParser::class),
        app(TidalApiClient::class),
        app(TidalClientCredentialsTokenService::class),
        app(TidalAdminTokenService::class),
    );

    $job2 = new ImportTidalTracksJob($pack->id, 'https://tidal.com/browse/track/123');
    $job2->handle(
        app(TidalUrlParser::class),
        app(TidalApiClient::class),
        app(TidalClientCredentialsTokenService::class),
        app(TidalAdminTokenService::class),
    );

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(1);
});
