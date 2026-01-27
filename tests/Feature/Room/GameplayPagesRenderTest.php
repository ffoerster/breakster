<?php

use App\Models\Room;
use App\Models\RoomPlayer;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the create room page', function () {
    $this->get('/room/create')->assertOk()->assertInertia(fn(Assert $page) => $page->component('CreateRoom'));
});

it('renders the join room page', function () {
    $this->get('/room/join')->assertOk()->assertInertia(fn(Assert $page) => $page->component('JoinRoom'));
});

it('renders room show for a non-member', function () {
    $room = Room::factory()->create();

    $this
        ->get("/room/{$room->code}")
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('Room/Show')->where('me', null));
});

it('renders room show for a session member', function () {
    $room = Room::factory()->create();
    $player = RoomPlayer::factory()->for($room)->create();

    $this
        ->withSession(['room_player_id' => $player->id])
        ->get("/room/{$room->code}")
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('Room/Show')->where('me.id', $player->id));
});

it('forbids room state for non-members', function () {
    $room = Room::factory()->create();

    $this->getJson("/room/{$room->code}/state")->assertForbidden();
});
