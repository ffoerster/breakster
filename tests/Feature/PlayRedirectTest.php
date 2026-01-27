<?php

use App\Models\Pack;
use App\Models\Provider;
use App\Models\Room;
use App\Models\Round;
use App\Models\Track;

it('redirects to the spotify track when provider matches', function () {
    $spotify = Provider::factory()->create([
        'key' => 'spotify',
        'name' => 'Spotify',
        'is_active' => true,
    ]);

    $pack = Pack::factory()->create();
    $track = Track::factory()->for($pack)->create([
        'provider_id' => $spotify->id,
        'provider_track_id' => '3n3Ppam7vgaVa1iaRUc9Lp',
        'external_url' => null,
    ]);

    $room = Room::factory()->for($pack)->create([
        'playback_provider_id' => $spotify->id,
    ]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'track_id' => $track->id,
    ]);

    $this->get("/play/{$room->code}/{$round->id}")->assertRedirect(
        'https://open.spotify.com/track/3n3Ppam7vgaVa1iaRUc9Lp',
    );
});

it('redirects to the tidal track when provider matches', function () {
    $tidal = Provider::factory()->tidal()->create();

    $pack = Pack::factory()->create();
    $track = Track::factory()->for($pack)->create([
        'provider_id' => $tidal->id,
        'provider_track_id' => '123456',
        'external_url' => null,
    ]);

    $room = Room::factory()->for($pack)->create([
        'playback_provider_id' => $tidal->id,
    ]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'track_id' => $track->id,
    ]);

    $this->get("/play/{$room->code}/{$round->id}")->assertRedirect('https://tidal.com/browse/track/123456');
});

it('redirects to provider search when track provider mismatches', function () {
    $spotify = Provider::factory()->create([
        'key' => 'spotify',
        'name' => 'Spotify',
        'is_active' => true,
    ]);

    $tidal = Provider::factory()->tidal()->create();

    $pack = Pack::factory()->create();
    $track = Track::factory()->for($pack)->create([
        'provider_id' => $tidal->id,
        'title' => 'Some Title',
        'artist' => 'Some Artist',
    ]);

    $room = Room::factory()->for($pack)->create([
        'playback_provider_id' => $spotify->id,
    ]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'track_id' => $track->id,
    ]);

    $query = urlencode('Some Artist Some Title');

    $this->get("/play/{$room->code}/{$round->id}")->assertRedirect("https://open.spotify.com/search/{$query}");
});

it('returns not found when the round does not belong to the room', function () {
    $spotify = Provider::factory()->create([
        'key' => 'spotify',
        'name' => 'Spotify',
        'is_active' => true,
    ]);

    $pack = Pack::factory()->create();
    $track = Track::factory()->for($pack)->create([
        'provider_id' => $spotify->id,
        'provider_track_id' => '3n3Ppam7vgaVa1iaRUc9Lp',
    ]);

    $roomA = Room::factory()->for($pack)->create([
        'playback_provider_id' => $spotify->id,
    ]);

    $roomB = Room::factory()->for($pack)->create([
        'playback_provider_id' => $spotify->id,
    ]);

    $round = Round::factory()->create([
        'room_id' => $roomA->id,
        'track_id' => $track->id,
    ]);

    $this->get("/play/{$roomB->code}/{$round->id}")->assertNotFound();
});
