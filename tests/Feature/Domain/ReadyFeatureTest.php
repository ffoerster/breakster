<?php

use App\Domain\Game\GameService;
use App\Events\PlayerReady;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Track;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->gameService = app(GameService::class);
    $this->provider = Provider::query()->firstOrCreate(['key' => 'tidal'], ['name' => 'TIDAL', 'is_active' => true]);
    $this->pack = Pack::factory()->create();
    Track::factory()
        ->count(20)
        ->for($this->pack)
        ->create();
});

/**
 * Helper to create a room in lobby state with the playback provider set.
 */
function createRoomInLobbyForReady($pack, $provider, $gameService, $guestDisplayName = 'Host'): Room
{
    $room = $gameService->createRoom($pack, guestDisplayName: $guestDisplayName);
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

describe('GameService::markReady', function () {
    it('marks a player as ready in the lobby', function () {
        $room = createRoomInLobbyForReady($this->pack, $this->provider, $this->gameService);
        $player = $room->players()->where('is_host', true)->first();

        Event::fake();

        $this->gameService->markReady($room, $player);

        expect($player->fresh()->is_ready)->toBeTrue();

        Event::assertDispatched(PlayerReady::class);
    });

    it('throws exception when room is not in lobby state', function () {
        $room = createRoomInLobbyForReady($this->pack, $this->provider, $this->gameService);

        // Start a round to move out of lobby state
        $this->gameService->startRound($room);
        $room->refresh();

        $player = $room->players()->where('is_host', true)->first();

        expect(fn () => $this->gameService->markReady($room, $player))
            ->toThrow(\RuntimeException::class, 'Ready can only be marked in the lobby.');
    });

    it('is idempotent - calling twice succeeds without error', function () {
        $room = createRoomInLobbyForReady($this->pack, $this->provider, $this->gameService);
        $player = $room->players()->where('is_host', true)->first();

        $this->gameService->markReady($room, $player);
        $this->gameService->markReady($room, $player);

        expect($player->fresh()->is_ready)->toBeTrue();
    });

    it('broadcasts PlayerReady event with correct counts', function () {
        $room = createRoomInLobbyForReady($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $player1 = $room->players()->where('is_host', true)->first();

        Event::fake();

        $this->gameService->markReady($room, $player1);

        Event::assertDispatched(PlayerReady::class, function ($event) use ($room, $player1) {
            return
                $event->roomCode === $room->code
                && $event->playerId === $player1->id
                && $event->readyCount === 1
                && $event->totalPlayers === 2;
        });
    });

    it('resets ready status when starting a round', function () {
        $room = createRoomInLobbyForReady($this->pack, $this->provider, $this->gameService);
        $player = $room->players()->where('is_host', true)->first();

        // Mark ready in lobby
        $this->gameService->markReady($room, $player);
        expect($player->fresh()->is_ready)->toBeTrue();

        // Start round
        $this->gameService->startRound($room);

        // Ready status should be reset
        expect($player->fresh()->is_ready)->toBeFalse();
    });
});

describe('Ready API endpoints', function () {
    it('marks player ready via POST /room/{code}/ready', function () {
        /** @var \Tests\TestCase $this */
        $room = Room::factory()->inLobby()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        $this->post("/room/{$room->code}/ready")->assertSuccessful();

        expect($player->fresh()->is_ready)->toBeTrue();
    });

    it('returns 409 when room is not in lobby', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        $this
            ->postJson("/room/{$room->code}/ready")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ready can only be marked in the lobby.');
    });
});

describe('RoomStateData ready status', function () {
    it('includes ready_count in room data', function () {
        /** @var \Tests\TestCase $this */
        $room = Room::factory()->inLobby()->create();

        $player1 = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id]);

        // Mark player1 as ready
        $player1->update(['is_ready' => true]);

        session()->put('room_player_id', $player2->id);

        $response = $this->getJson("/room/{$room->code}/state");
        $response->assertSuccessful();

        expect($response->json('room.ready_count'))->toBe(1)
            ->and($response->json('room.players_count'))->toBe(2);
    });

    it('includes is_ready in me data', function () {
        /** @var \Tests\TestCase $this */
        $room = Room::factory()->inLobby()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        // Before marking ready
        $response1 = $this->getJson("/room/{$room->code}/state");
        expect($response1->json('me.is_ready'))->toBeFalse();

        // After marking ready
        $player->update(['is_ready' => true]);

        $response2 = $this->getJson("/room/{$room->code}/state");
        expect($response2->json('me.is_ready'))->toBeTrue();
    });
});

describe('RoomPlayer ready helpers', function () {
    it('markReady sets is_ready to true', function () {
        $room = Room::factory()->inLobby()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        expect($player->is_ready)->toBeFalse();

        $player->markReady();

        expect($player->is_ready)->toBeTrue();
    });
});
