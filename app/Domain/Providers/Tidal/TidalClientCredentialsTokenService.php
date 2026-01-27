<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Tidal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TidalClientCredentialsTokenService
{
    private const TOKEN_CACHE_KEY = 'tidal.client_credentials.access_token';

    private const TOKEN_ENDPOINT = 'https://auth.tidal.com/v1/oauth2/token';

    public function __construct(
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
    ) {}

    public function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheToken();
    }

    public function clearCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    private function fetchAndCacheToken(): string
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to obtain TIDAL access token: '.$response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from TIDAL: '.$response->body());
        }

        $token = (string) $data['access_token'];
        $expiresIn = (int) $data['expires_in'];
        $ttl = max(1, $expiresIn - 60);

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }
}
