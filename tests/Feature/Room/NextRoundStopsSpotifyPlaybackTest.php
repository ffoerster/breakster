<?php

use App\Domain\Game\Enums\RoomState;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\Track;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

it('pauses Spotify playback when host starts next round', function () {
    /** @var \Tests\TestCase $this */
    Http::fake([
        'https://api.spotify.com/v1/me/player/pause*' => Http::response(null, 204),
    ]);

    $pack = Pack::factory()->create();
    Track::factory()
        ->count(3)
        ->for($pack)
        ->create();

    $spotify = Provider::factory()->create([
        'key' => 'spotify',
        'name' => 'Spotify',
        'is_active' => true,
    ]);

    $room = Room::factory()
        ->inReveal()
        ->create([
            'pack_id' => $pack->id,
            'playback_provider_id' => $spotify->id,
            'spotify_device_id' => 'device_123',
            'state' => RoomState::Reveal,
        ]);

    $round = Round::factory()
        ->revealed()
        ->create([
            'room_id' => $room->id,
            'track_id' => $pack->tracks()->inRandomOrder()->first()->id,
        ]);

    $room->update([
        'current_round_id' => $round->id,
    ]);

    $host = RoomPlayer::factory()
        ->asHost()
        ->create([
            'room_id' => $room->id,
        ]);

    session()->put('room_player_id', $host->id);
    session()->put('spotify.guest_token', [
        'access_token' => 'test_access_token',
        'refresh_token' => 'test_refresh_token',
        'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
    ]);

    $this->post("/room/{$room->code}/next")->assertSuccessful();

    Http::assertSent(function ($request) {
        return (
            $request->method() === 'PUT'
            && str_contains($request->url(), 'https://api.spotify.com/v1/me/player/pause')
            && str_contains($request->url(), 'device_id=device_123')
            && $request->hasHeader('Authorization', 'Bearer test_access_token')
        );
    });
});
