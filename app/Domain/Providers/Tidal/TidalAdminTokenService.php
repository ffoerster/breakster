<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Tidal;

use App\Models\ProviderToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TidalAdminTokenService
{
    private const TOKEN_ENDPOINT = 'https://auth.tidal.com/v1/oauth2/token';

    public function __construct(
        private readonly string $clientId,
        #[\SensitiveParameter]
        private readonly string $clientSecret,
    ) {}

    public function hasValidToken(): bool
    {
        $token = $this->getLatestAdminToken();

        if (!$token) {
            return false;
        }

        return $token->expires_at?->isAfter(now()->addSeconds(60)) === true;
    }

    public function getAccessTokenForAdmin(): string
    {
        $token = $this->getLatestAdminToken();

        if (!$token) {
            throw new DomainException('Connect TIDAL to import playlists');
        }

        if ($token->expires_at?->isAfter(now()->addSeconds(60)) === true) {
            return $token->access_token;
        }

        $this->refreshToken($token);

        $fresh = $token->fresh();

        if (!$fresh) {
            throw new RuntimeException('Failed to reload refreshed TIDAL token.');
        }

        return $fresh->access_token;
    }

    public function refreshToken(#[\SensitiveParameter] ProviderToken $token): void
    {
        if (!is_string($token->refresh_token) || $token->refresh_token === '') {
            throw new DomainException('TIDAL admin token cannot be refreshed. Please reconnect TIDAL.');
        }

        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to refresh TIDAL token: '.$response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from TIDAL: '.$response->body());
        }

        $expiresAt = CarbonImmutable::now()->addSeconds((int) $data['expires_in']);

        $token->forceFill([
            'access_token' => (string) $data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : $token->refresh_token,
            'expires_at' => $expiresAt,
            'scopes' => $this->normalizeScopes($data['scope'] ?? $token->scopes),
        ])->save();
    }

    public function storeTokenForUser(
        User $user,
        #[\SensitiveParameter] string $accessToken,
        #[\SensitiveParameter] ?string $refreshToken,
        CarbonImmutable $expiresAt,
        array $scopes,
    ): ProviderToken {
        return ProviderToken::query()->updateOrCreate([
            'provider_key' => 'tidal',
            'user_id' => $user->id,
        ], [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'scopes' => $scopes,
        ]);
    }

    private function getLatestAdminToken(): ?ProviderToken
    {
        return ProviderToken::query()
            ->where('provider_key', 'tidal')
            ->whereHas('user', static function ($query): void {
                $query->where('is_admin', true);
            })
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @param  array<string>|string|null  $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array|string|null $scopes): array
    {
        if (is_array($scopes)) {
            return array_values(array_filter(array_map('strval', $scopes)));
        }

        if (is_string($scopes) && $scopes !== '') {
            return array_values(array_filter(preg_split('/\s+/', trim($scopes)) ?: []));
        }

        return [];
    }
}
