<?php

use App\Domain\Game\Enums\RoomState;
use App\Models\Pack;
use App\Models\ProviderToken;
use App\Models\Room;
use App\Models\User;

it('allows host to set spotify device for room', function () {
    $host = User::factory()->create(['is_admin' => true]);
    $pack = Pack::factory()->create();

    $room = Room::factory()->create([
        'host_user_id' => $host->id,
        'pack_id' => $pack->id,
        'state' => RoomState::Lobby,
    ]);

    // Create a Spotify provider token for the host
    ProviderToken::create([
        'provider_key' => 'spotify',
        'user_id' => $host->id,
        'access_token' => 'test_access_token',
        'refresh_token' => 'test_refresh_token',
        'expires_at' => now()->addHour(),
        'scopes' => ['user-modify-playback-state', 'user-read-playback-state'],
    ]);

    $response = $this->actingAs($host)->postJson("/room/{$room->code}/spotify/device", [
        'device_id' => 'test_device_123',
    ]);

    $response->assertSuccessful();
    expect($room->fresh()->spotify_device_id)->toBe('test_device_123');
});

it('prevents non-host from setting spotify device', function () {
    $host = User::factory()->create();
    $otherUser = User::factory()->create();
    $pack = Pack::factory()->create();

    $room = Room::factory()->create([
        'host_user_id' => $host->id,
        'pack_id' => $pack->id,
        'state' => RoomState::Lobby,
    ]);

    $response = $this->actingAs($otherUser)->postJson("/room/{$room->code}/spotify/device", [
        'device_id' => 'test_device_123',
    ]);

    $response->assertForbidden();
});

it('requires authentication to access spotify device endpoints', function () {
    $pack = Pack::factory()->create();
    $room = Room::factory()->create([
        'pack_id' => $pack->id,
        'state' => RoomState::Lobby,
    ]);

    $response = $this->getJson("/room/{$room->code}/spotify/devices");

    $response->assertForbidden();
});
