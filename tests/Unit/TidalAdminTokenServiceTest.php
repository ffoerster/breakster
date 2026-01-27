<?php

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Models\ProviderToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('TidalAdminTokenService', function () {
    it('refreshes admin token when expired', function () {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $token = ProviderToken::create([
            'provider_key' => 'tidal',
            'user_id' => $admin->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token-123',
            'expires_at' => now()->subMinute(),
            'scopes' => ['playlists.read', 'user.read'],
        ]);

        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'refresh-token-456',
                'expires_in' => 3600,
                'scope' => 'playlists.read user.read',
            ], 200),
        ]);

        $service = new TidalAdminTokenService(clientId: 'test-client-id', clientSecret: 'test-client-secret');

        $accessToken = $service->getAccessTokenForAdmin();

        expect($accessToken)->toBe('new-access-token');

        $token->refresh();
        expect($token->access_token)->toBe('new-access-token');
        expect($token->refresh_token)->toBe('refresh-token-456');
        expect($token->expires_at->isAfter(now()))->toBeTrue();

        Http::assertSent(function ($request) {
            return (
                $request->url() === 'https://auth.tidal.com/v1/oauth2/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'refresh-token-123'
            );
        });
    });
});
