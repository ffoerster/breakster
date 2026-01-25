<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Providers\Spotify\SpotifyAdminTokenService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SpotifyAuthController extends Controller
{
    private const AUTHORIZE_ENDPOINT = 'https://accounts.spotify.com/authorize';

    private const TOKEN_ENDPOINT = 'https://accounts.spotify.com/api/token';

    public function connect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isGuest = !$user;

        $clientId = config('services.spotify.client_id');
        $redirectUri = config('services.spotify.redirect_uri');

        if (!is_string($clientId) || $clientId === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured.');
        }

        if (!is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('SPOTIFY_REDIRECT_URI is not configured.');
        }

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
        $state = Str::random(40);

        $request->session()->put([
            'spotify.oauth.state' => $state,
            'spotify.oauth.code_verifier' => $codeVerifier,
            'spotify.oauth.redirect' => $request->string('redirect')->toString(),
            'spotify.oauth.is_guest' => $isGuest,
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'playlist-read-private playlist-read-collaborative user-modify-playback-state user-read-playback-state',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return redirect()->away(self::AUTHORIZE_ENDPOINT . '?' . $query);
    }

    public function callback(Request $request, SpotifyAdminTokenService $adminTokenService): RedirectResponse
    {
        $user = $request->user();
        $isGuest = $request->session()->pull('spotify.oauth.is_guest', false);

        $expectedState = $request->session()->pull('spotify.oauth.state');
        $codeVerifier = $request->session()->pull('spotify.oauth.code_verifier');
        $redirectTo = $request->session()->pull('spotify.oauth.redirect') ?: ($isGuest ? '/' : '/admin');

        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();

        abort_unless(is_string($expectedState) && hash_equals($expectedState, $state), 419);
        abort_unless(is_string($codeVerifier) && $codeVerifier !== '', 419);
        abort_unless($code !== '', 400);

        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');
        $redirectUri = config('services.spotify.redirect_uri');

        if (!is_string($clientId) || $clientId === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_ID is not configured.');
        }

        if (!is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('SPOTIFY_CLIENT_SECRET is not configured.');
        }

        if (!is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('SPOTIFY_REDIRECT_URI is not configured.');
        }

        $credentials = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'code_verifier' => $codeVerifier,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to obtain Spotify token: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from Spotify: ' . $response->body());
        }

        $expiresAt = CarbonImmutable::now()->addSeconds((int) $data['expires_in']);
        $scopes = $this->normalizeScopes($data['scope'] ?? null);

        if ($isGuest) {
            // Store tokens in session for guest users
            $request->session()->put('spotify.guest_token', [
                'access_token' => (string) $data['access_token'],
                'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
                'expires_at' => $expiresAt->toIso8601String(),
                'scopes' => $scopes,
            ]);
        } else {
            // Store in database for authenticated users
            $adminTokenService->storeTokenForUser(
                user: $user,
                accessToken: (string) $data['access_token'],
                refreshToken: isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
                expiresAt: $expiresAt,
                scopes: $scopes,
            );
        }

        $request->session()->flash('spotify.connected', true);

        return redirect()->to($redirectTo);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            // Remove from database for authenticated users
            $user->providerTokens()->where('provider_key', 'spotify')->delete();
        } else {
            // Remove from session for guests
            $request->session()->forget('spotify.guest_token');
        }

        $request->session()->flash('spotify.disconnected', true);

        return redirect()->back();
    }

    private function generateCodeVerifier(): string
    {
        $verifier = $this->base64UrlEncode(random_bytes(64));

        if (strlen($verifier) > 128) {
            $verifier = substr($verifier, 0, 128);
        }

        if (strlen($verifier) < 43) {
            $verifier = str_pad($verifier, 43, 'A');
        }

        return $verifier;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(?string $scopes): array
    {
        if (!is_string($scopes) || $scopes === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($scopes)) ?: []));
    }
}
