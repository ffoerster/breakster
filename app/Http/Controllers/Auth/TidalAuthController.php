<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TidalAuthController extends Controller
{
    private const AUTHORIZE_ENDPOINT = 'https://login.tidal.com/authorize';

    private const TOKEN_ENDPOINT = 'https://auth.tidal.com/v1/oauth2/token';

    public function connect(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin === true, 403);

        $clientId = config('services.tidal.client_id');
        $redirectUri = config('services.tidal.redirect_uri');

        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('TIDAL_CLIENT_ID is not configured.');
        }

        if (! is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('TIDAL_REDIRECT_URI is not configured.');
        }

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
        $state = Str::random(40);

        $request->session()->put([
            'tidal.oauth.state' => $state,
            'tidal.oauth.code_verifier' => $codeVerifier,
            'tidal.oauth.redirect' => $request->string('redirect')->toString(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'playlists.read user.read',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return redirect()->away(self::AUTHORIZE_ENDPOINT.'?'.$query);
    }

    public function callback(Request $request, TidalAdminTokenService $adminTokenService): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin === true, 403);

        $expectedState = $request->session()->pull('tidal.oauth.state');
        $codeVerifier = $request->session()->pull('tidal.oauth.code_verifier');
        $redirectTo = $request->session()->pull('tidal.oauth.redirect') ?: '/admin';

        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();

        abort_unless(is_string($expectedState) && hash_equals($expectedState, $state), 419);
        abort_unless(is_string($codeVerifier) && $codeVerifier !== '', 419);
        abort_unless($code !== '', 400);

        $clientId = config('services.tidal.client_id');
        $clientSecret = config('services.tidal.client_secret');
        $redirectUri = config('services.tidal.redirect_uri');

        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('TIDAL_CLIENT_ID is not configured.');
        }

        if (! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('TIDAL_CLIENT_SECRET is not configured.');
        }

        if (! is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('TIDAL_REDIRECT_URI is not configured.');
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
                'client_secret' => $clientSecret,
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to obtain TIDAL token: '.$response->body());
        }

        $data = $response->json();

        if (! isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid token response from TIDAL: '.$response->body());
        }

        $expiresAt = CarbonImmutable::now()->addSeconds((int) $data['expires_in']);
        $scopes = $this->normalizeScopes($data['scope'] ?? null);

        $adminTokenService->storeTokenForUser(
            user: $user,
            accessToken: (string) $data['access_token'],
            refreshToken: isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            expiresAt: $expiresAt,
            scopes: $scopes,
        );

        $request->session()->flash('tidal.connected', true);

        return redirect()->to($redirectTo);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin === true, 403);

        $user->providerTokens()
            ->where('provider_key', 'tidal')
            ->delete();

        $request->session()->flash('tidal.disconnected', true);

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
        if (! is_string($scopes) || $scopes === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($scopes)) ?: []));
    }
}
