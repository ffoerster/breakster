<?php

use App\Domain\Providers\Spotify\SpotifyAdminTokenService;
use App\Domain\Providers\Spotify\SpotifyApiClient;
use App\Domain\Providers\Spotify\SpotifyClientCredentialsTokenService;
use App\Domain\Providers\Spotify\SpotifyUrlParser;
use App\Jobs\ImportSpotifyTracksJob;
use App\Models\Pack;
use App\Models\Track;
use Illuminate\Support\Facades\Http;

it('imports a Spotify track into a pack', function () {
    config()->set('services.spotify.client_id', 'test-client-id');
    config()->set('services.spotify.client_secret', 'test-client-secret');
    config()->set('services.spotify.redirect_uri', 'https://breakster.ddev.site/auth/spotify/callback');
    config()->set('services.spotify.market', 'DE');

    Http::fake([
        'accounts.spotify.com/*' => Http::response([
            'access_token' => 'cc-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'api.spotify.com/v1/tracks/*' => Http::response([
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
            'name' => 'Test Track',
            'artists' => [['name' => 'Test Artist']],
            'album' => [
                'id' => 'album123',
                'release_date' => '2024-01-15',
                'release_date_precision' => 'day',
                'images' => [['url' => 'https://i.scdn.co/image/test']],
            ],
            'preview_url' => 'https://p.scdn.co/mp3-preview/test',
            'external_urls' => ['spotify' => 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh'],
        ], 200),
        'api.spotify.com/v1/albums/*' => Http::response([
            'label' => 'Test Records',
        ], 200),
    ]);

    $pack = Pack::factory()->create();

    $job = new ImportSpotifyTracksJob($pack->id, 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
    $job->handle(
        app(SpotifyUrlParser::class),
        app(SpotifyApiClient::class),
        app(SpotifyClientCredentialsTokenService::class),
        app(SpotifyAdminTokenService::class),
    );

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(1);
    expect($pack->fresh()->spotify_source)->toBe('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');

    $track = Track::query()->where('pack_id', $pack->id)->first();
    expect($track->title)->toBe('Test Track');
    expect($track->artist)->toBe('Test Artist');
    expect($track->label)->toBe('Test Records');
    expect($track->external_url)->toBe('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
});

it('does not create duplicates when importing the same source twice', function () {
    config()->set('services.spotify.client_id', 'test-client-id');
    config()->set('services.spotify.client_secret', 'test-client-secret');
    config()->set('services.spotify.redirect_uri', 'https://breakster.ddev.site/auth/spotify/callback');
    config()->set('services.spotify.market', 'DE');

    Http::fake([
        'accounts.spotify.com/*' => Http::response([
            'access_token' => 'cc-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'api.spotify.com/v1/tracks/*' => Http::response([
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
            'name' => 'Test Track',
            'artists' => [['name' => 'Test Artist']],
            'album' => [
                'id' => 'album123',
                'release_date' => '2024-01-15',
                'release_date_precision' => 'day',
                'images' => [['url' => 'https://i.scdn.co/image/test']],
            ],
            'preview_url' => null,
            'external_urls' => ['spotify' => 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh'],
        ], 200),
        'api.spotify.com/v1/albums/*' => Http::response([
            'label' => 'Test Records',
        ], 200),
    ]);

    $pack = Pack::factory()->create();

    $job = new ImportSpotifyTracksJob($pack->id, 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
    $job->handle(
        app(SpotifyUrlParser::class),
        app(SpotifyApiClient::class),
        app(SpotifyClientCredentialsTokenService::class),
        app(SpotifyAdminTokenService::class),
    );

    $job2 = new ImportSpotifyTracksJob($pack->id, 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
    $job2->handle(
        app(SpotifyUrlParser::class),
        app(SpotifyApiClient::class),
        app(SpotifyClientCredentialsTokenService::class),
        app(SpotifyAdminTokenService::class),
    );

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(1);
});

it('handles tracks without preview_url gracefully', function () {
    config()->set('services.spotify.client_id', 'test-client-id');
    config()->set('services.spotify.client_secret', 'test-client-secret');
    config()->set('services.spotify.redirect_uri', 'https://breakster.ddev.site/auth/spotify/callback');
    config()->set('services.spotify.market', 'DE');

    Http::fake([
        'accounts.spotify.com/*' => Http::response([
            'access_token' => 'cc-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'api.spotify.com/v1/tracks/*' => Http::response([
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
            'name' => 'Track Without Preview',
            'artists' => [['name' => 'Artist']],
            'album' => [
                'id' => 'album123',
                'release_date' => '2024-01-15',
                'release_date_precision' => 'day',
                'images' => [['url' => 'https://i.scdn.co/image/test']],
            ],
            'preview_url' => null,
            'external_urls' => ['spotify' => 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh'],
        ], 200),
        'api.spotify.com/v1/albums/*' => Http::response([
            'label' => 'Test Records',
        ], 200),
    ]);

    $pack = Pack::factory()->create();

    $job = new ImportSpotifyTracksJob($pack->id, 'https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
    $job->handle(
        app(SpotifyUrlParser::class),
        app(SpotifyApiClient::class),
        app(SpotifyClientCredentialsTokenService::class),
        app(SpotifyAdminTokenService::class),
    );

    $track = Track::query()->where('pack_id', $pack->id)->first();
    expect($track)->not->toBeNull();
    expect($track->preview_url)->toBeNull();
    expect($track->external_url)->toBe('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');
});
