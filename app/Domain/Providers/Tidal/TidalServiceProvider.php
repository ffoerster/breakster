<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Tidal;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class TidalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TidalClientCredentialsTokenService::class, static function (Application $app) {
            $clientId = config('services.tidal.client_id');
            $clientSecret = config('services.tidal.client_secret');

            if (!is_string($clientId) || $clientId === '') {
                throw new \RuntimeException('TIDAL_CLIENT_ID is not configured. Please set it in your .env file.');
            }

            if (!is_string($clientSecret) || $clientSecret === '') {
                throw new \RuntimeException('TIDAL_CLIENT_SECRET is not configured. Please set it in your .env file.');
            }

            return new TidalClientCredentialsTokenService(clientId: $clientId, clientSecret: $clientSecret);
        });

        $this->app->singleton(TidalAdminTokenService::class, static function (Application $app) {
            $clientId = config('services.tidal.client_id');
            $clientSecret = config('services.tidal.client_secret');

            if (!is_string($clientId) || $clientId === '') {
                throw new \RuntimeException('TIDAL_CLIENT_ID is not configured. Please set it in your .env file.');
            }

            if (!is_string($clientSecret) || $clientSecret === '') {
                throw new \RuntimeException('TIDAL_CLIENT_SECRET is not configured. Please set it in your .env file.');
            }

            return new TidalAdminTokenService(clientId: $clientId, clientSecret: $clientSecret);
        });

        $this->app->singleton(TidalApiClient::class, static function (): TidalApiClient {
            $countryCode = config('services.tidal.country_code', 'DE');

            return new TidalApiClient(
                countryCode: is_string($countryCode) && $countryCode !== '' ? $countryCode : 'DE',
            );
        });
    }
}
