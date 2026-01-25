<?php

declare(strict_types=1);

namespace App\Domain\Providers\Spotify;

use App\Models\ProviderToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyPlaybackService
{
    private const TOKEN_ENDPOINT = 'https://accounts.spotify.com/api/token';

    public function __construct(
        private readonly SpotifyApiClient $apiClient,
    ) {}

    /**
     * Get available Spotify devices for a user or guest.
     *
     * @return array{devices: array<int, array{id: string, name: string, type: string, is_active: bool}>}
     */
    public function getDevices(?User $user = null): array
    {
        $token = $this->getValidToken($user);

        return $this->apiClient->getDevices($token);
    }

    /**
     * Transfer playback to a specific device.
     */
    public function transferPlayback(?User $user, string $deviceId, bool $play = false): void
    {
        $token = $this->getValidToken($user);

        $this->apiClient->transferPlayback($deviceId, $token, $play);
    }

    /**
     * Start playback of a track on a device.
     */
    public function startPlayback(?User $user, string $deviceId, string $trackUri): void
    {
        $token = $this->getValidToken($user);

        $this->apiClient->startPlayback($deviceId, $trackUri, $token);
    }

    /**
     * Pause playback on a device.
     */
    public function pausePlayback(?User $user, string $deviceId): void
    {
        $token = $this->getValidToken($user);

        $this->apiClient->pausePlayback($deviceId, $token);
    }

    /**
     * Get a valid access token for the user or guest, refreshing if necessary.
     */
    private function getValidToken(?User $user): string
    {
        // Try guest session first if no user provided
        if (!$user) {
            return $this->getGuestToken();
        }

        $providerToken = $user->providerTokens()->where('provider_key', 'spotify')->first();

        if (!$providerToken) {
            // Fallback to guest token if user has no token
            return $this->getGuestToken();
        }

        // Check if token needs refresh (refresh 5 minutes early to be safe)
        if ($providerToken->expires_at->subMinutes(5)->isPast()) {
            $this->refreshToken($providerToken);
            $providerToken->refresh();
        }

        return $providerToken->access_token;
    }

    /**
     * Get guest token from session.
     */
    private function getGuestToken(): string
    {
        $guestToken = session('spotify.guest_token');

        if (!$guestToken || !isset($guestToken['access_token'])) {
            throw new RuntimeException('Guest has not connected Spotify.');
        }

        // Check if token needs refresh
        $expiresAt = CarbonImmutable::parse($guestToken['expires_at']);
        if ($expiresAt->subMinutes(5)->isPast() && isset($guestToken['refresh_token'])) {
            $this->refreshGuestToken($guestToken['refresh_token']);
            $guestToken = session('spotify.guest_token');
        }

        return $guestToken['access_token'];
    }

    /**
     * Refresh an expired access token.
     */
    private function refreshToken(ProviderToken $providerToken): void
    {
        if (!$providerToken->refresh_token) {
            throw new RuntimeException('No refresh token available for Spotify.');
        }

        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (!is_string($clientId) || $clientId === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured.');
        }

        if (!is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_SECRET is not configured.');
        }

        $credentials = base64_encode("{$clientId}:{$clientSecret}");

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $providerToken->refresh_token,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to refresh Spotify token: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from Spotify: ' . $response->body());
        }

        $expiresAt = CarbonImmutable::now()->addSeconds((int) $data['expires_in']);

        $providerToken->update([
            'access_token' => (string) $data['access_token'],
            'expires_at' => $expiresAt,
            // Refresh token may or may not be included in refresh response
            'refresh_token' => isset($data['refresh_token'])
                ? (string) $data['refresh_token']
                : $providerToken->refresh_token,
        ]);
    }

    /**
     * Refresh a guest's expired access token stored in session.
     */
    private function refreshGuestToken(string $refreshToken): void
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (!is_string($clientId) || $clientId === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured.');
        }

        if (!is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_SECRET is not configured.');
        }

        $credentials = base64_encode("{$clientId}:{$clientSecret}");

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to refresh guest Spotify token: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from Spotify: ' . $response->body());
        }

        $expiresAt = CarbonImmutable::now()->addSeconds((int) $data['expires_in']);

        // Update session
        session()->put('spotify.guest_token', [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : $refreshToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'scopes' => session('spotify.guest_token.scopes', []),
        ]);
    }
}
