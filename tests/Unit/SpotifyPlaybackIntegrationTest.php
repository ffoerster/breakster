<?php

use App\Domain\Game\GameService;
use App\Domain\Providers\Spotify\SpotifyPlaybackService;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not throw when spotify playback fails during round start', function () {
    $spotify = Provider::factory()->create(['key' => 'spotify', 'is_active' => true]);
    $pack = Pack::factory()->create();
    Track::factory()
        ->count(3)
        ->create([
            'pack_id' => $pack->id,
            'provider_id' => $spotify->id,
        ]);

    $host = User::factory()->create();
    $gameService = app(GameService::class);
    $room = $gameService->createRoom($pack, host: $host);
    $room->update([
        'playback_provider_id' => $spotify->id,
        'spotify_device_id' => 'test_device',
    ]);
    $room = $room->fresh();

    // Mock the SpotifyPlaybackService to throw an exception
    $mockPlayback = Mockery::mock(SpotifyPlaybackService::class);
    $mockPlayback->shouldReceive('startPlayback')->andThrow(new RuntimeException('Spotify Premium required'));
    app()->instance(SpotifyPlaybackService::class, $mockPlayback);

    // Should not throw - failure should be logged but round should start
    $round = $gameService->startRound($room);

    expect($round)->not->toBeNull();
    expect($round->track_id)->toBeInt();
});
