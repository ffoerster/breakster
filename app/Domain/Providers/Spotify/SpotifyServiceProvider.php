<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Spotify;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class SpotifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SpotifyClientCredentialsTokenService::class, static function (Application $app) {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');

            if (!is_string($clientId) || $clientId === '') {
                throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured. Please set it in your .env file.');
            }

            if (!is_string($clientSecret) || $clientSecret === '') {
                throw new RuntimeException('SPOTIFY_CLIENT_SECRET is not configured. Please set it in your .env file.');
            }

            return new SpotifyClientCredentialsTokenService(clientId: $clientId, clientSecret: $clientSecret);
        });

        $this->app->singleton(SpotifyAdminTokenService::class, static function (Application $app) {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');

            if (!is_string($clientId) || $clientId === '') {
                throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured. Please set it in your .env file.');
            }

            if (!is_string($clientSecret) || $clientSecret === '') {
                throw new RuntimeException('SPOTIFY_CLIENT_SECRET is not configured. Please set it in your .env file.');
            }

            return new SpotifyAdminTokenService(clientId: $clientId, clientSecret: $clientSecret);
        });

        $this->app->singleton(SpotifyApiClient::class, static function (): SpotifyApiClient {
            $market = config('services.spotify.market', 'DE');

            return new SpotifyApiClient(market: is_string($market) && $market !== '' ? $market : 'DE');
        });
    }
}
