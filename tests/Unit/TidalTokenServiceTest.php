<?php

use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('TidalClientCredentialsTokenService', function () {
    beforeEach(function () {
        Cache::flush();
        $this->service = new TidalClientCredentialsTokenService(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
        );
    });

    it('fetches and caches access token', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $token = $this->service->getAccessToken();

        expect($token)->toBe('test-token-123');

        Http::assertSent(function ($request) {
            return (
                $request->url() === 'https://auth.tidal.com/v1/oauth2/token'
                && $request->hasHeader('Authorization')
                && str_contains($request->header('Authorization')[0], 'Basic')
                && $request['grant_type'] === 'client_credentials'
            );
        });
    });

    it('returns cached token without making new request', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $token1 = $this->service->getAccessToken();
        $token2 = $this->service->getAccessToken();

        expect($token1)->toBe($token2);
        Http::assertSentCount(1);
    });

    it('caches token with proper TTL', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $this->service->getAccessToken();

        expect(Cache::has('tidal.client_credentials.access_token'))->toBeTrue();
    });

    it('throws exception on failed token request', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Invalid credentials',
            ], 401),
        ]);

        $this->service->getAccessToken();
    })->throws(RuntimeException::class, 'Failed to obtain TIDAL access token');

    it('throws exception on invalid token response', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'invalid' => 'response',
            ], 200),
        ]);

        $this->service->getAccessToken();
    })->throws(RuntimeException::class, 'Invalid token response from TIDAL');

    it('uses correct authorization header format', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->service->getAccessToken();

        Http::assertSent(function ($request) {
            $expected = 'Basic ' . base64_encode('test-client-id:test-client-secret');

            return $request->header('Authorization')[0] === $expected;
        });
    });

    it('can clear cached token', function () {
        Http::fake([
            'auth.tidal.com/*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->service->getAccessToken();
        expect(Cache::has('tidal.client_credentials.access_token'))->toBeTrue();

        $this->service->clearCache();
        expect(Cache::has('tidal.client_credentials.access_token'))->toBeFalse();
    });
});
