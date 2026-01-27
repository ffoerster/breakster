<?php

use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Game\GameService;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\ReadyConfirmation;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
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
 * Helper to create a room with the playback provider set.
 */
function createRoomWithProviderForReady($pack, $provider, $gameService, $guestDisplayName = 'Host'): Room
{
    $room = $gameService->createRoom($pack, guestDisplayName: $guestDisplayName);
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

describe('GameService::markReady', function () {
    it('marks a player as ready for the current round', function () {
        $room = createRoomWithProviderForReady($this->pack, $this->provider, $this->gameService);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        // Clear placement_unlocks_at to allow immediate ready
        $round->update(['placement_unlocks_at' => now()->subSecond()]);

        Event::fake();

        $confirmation = $this->gameService->markReady($round->fresh(), $player);

        expect($confirmation)
            ->toBeInstanceOf(ReadyConfirmation::class)
            ->and($confirmation->round_id)
            ->toBe($round->id)
            ->and($confirmation->room_player_id)
            ->toBe($player->id)
            ->and($confirmation->confirmed_at)
            ->not->toBeNull();

        Event::assertDispatched(\App\Events\PlayerReady::class);
    });

    it('throws exception when round is not active', function () {
        $room = createRoomWithProviderForReady($this->pack, $this->provider, $this->gameService);

        $round = $this->gameService->startRound($room);
        $round->update(['status' => RoundStatus::Revealed]);
        $player = $room->players()->where('is_host', true)->first();

        expect(fn() => $this->gameService->markReady($round->fresh(), $player))
            ->toThrow(\RuntimeException::class, 'This round is not active.');
    });

    it('throws exception when placement has not unlocked', function () {
        $room = createRoomWithProviderForReady($this->pack, $this->provider, $this->gameService);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        // Ensure placement is still locked (future timestamp)
        $round->update(['placement_unlocks_at' => now()->addMinutes(5)]);

        expect(fn() => $this->gameService->markReady($round->fresh(), $player))
            ->toThrow(\RuntimeException::class, 'Ready check is not yet available.');
    });

    it('is idempotent - calling twice does not create duplicate', function () {
        $room = createRoomWithProviderForReady($this->pack, $this->provider, $this->gameService);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        $round->update(['placement_unlocks_at' => now()->subSecond()]);

        $this->gameService->markReady($round->fresh(), $player);
        $this->gameService->markReady($round->fresh(), $player);

        expect(ReadyConfirmation::where('round_id', $round->id)->where('room_player_id', $player->id)->count())
            ->toBe(1);
    });

    it('broadcasts PlayerReady event with correct counts', function () {
        $room = createRoomWithProviderForReady($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $round->update(['placement_unlocks_at' => now()->subSecond()]);

        Event::fake();

        $this->gameService->markReady($round->fresh(), $player1);

        Event::assertDispatched(\App\Events\PlayerReady::class, function ($event) use ($round, $player1) {
            return (
                $event->roundId === $round->id
                && $event->playerId === $player1->id
                && $event->readyCount === 1
                && $event->totalPlayers === 2
            );
        });
    });
});

describe('Ready API endpoints', function () {
    it('marks player ready via POST /room/{code}/ready', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'placement_unlocks_at' => now()->subSecond(),
            ]);
        $room->update(['current_round_id' => $round->id]);

        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        $this->post("/room/{$room->code}/ready")->assertSuccessful();

        $this->assertDatabaseHas('ready_confirmations', [
            'round_id' => $round->id,
            'room_player_id' => $player->id,
        ]);
    });

    it('returns 409 when round is not active', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->revealed()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'placement_unlocks_at' => now()->subSecond(),
            ]);
        $room->update(['current_round_id' => $round->id]);

        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        $this->post("/room/{$room->code}/ready")->assertStatus(409);
    });

    it('returns 409 when placement is still locked', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'placement_unlocks_at' => now()->addMinutes(5),
            ]);
        $room->update(['current_round_id' => $round->id]);

        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        $this
            ->postJson("/room/{$room->code}/ready")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ready check is not yet available.');
    });
});

describe('RoomStateData ready status', function () {
    it('includes ready_count in round data', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'placement_unlocks_at' => now()->subSecond(),
            ]);
        $room->update(['current_round_id' => $round->id]);

        $player1 = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id]);

        // Mark player1 as ready
        ReadyConfirmation::create([
            'round_id' => $round->id,
            'room_player_id' => $player1->id,
            'confirmed_at' => now(),
        ]);

        session()->put('room_player_id', $player2->id);

        $response = $this->getJson("/room/{$room->code}/state");
        $response->assertSuccessful();

        expect($response->json('round.ready_count'))->toBe(1)->and($response->json('round.players_count'))->toBe(2);
    });

    it('includes is_ready in me data', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        session()->put('room_player_id', $player->id);

        // Before marking ready
        $response1 = $this->getJson("/room/{$room->code}/state");
        expect($response1->json('me.is_ready'))->toBeFalse();

        // After marking ready
        ReadyConfirmation::create([
            'round_id' => $round->id,
            'room_player_id' => $player->id,
            'confirmed_at' => now(),
        ]);

        $response2 = $this->getJson("/room/{$room->code}/state");
        expect($response2->json('me.is_ready'))->toBeTrue();
    });
});

describe('Round model ready helpers', function () {
    it('readyConfirmations returns correct count', function () {
        $round = Round::factory()->active()->create();
        $player1 = RoomPlayer::factory()->create(['room_id' => $round->room_id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $round->room_id]);

        expect($round->readyConfirmations()->count())->toBe(0);

        ReadyConfirmation::create([
            'round_id' => $round->id,
            'room_player_id' => $player1->id,
            'confirmed_at' => now(),
        ]);

        expect($round->fresh()->readyConfirmations()->count())->toBe(1);

        ReadyConfirmation::create([
            'round_id' => $round->id,
            'room_player_id' => $player2->id,
            'confirmed_at' => now(),
        ]);

        expect($round->fresh()->readyConfirmations()->count())->toBe(2);
    });
});

describe('RoomPlayer ready helpers', function () {
    it('isReadyForRound returns correct value', function () {
        $room = Room::factory()->inRound()->create();
        $round = Round::factory()->active()->create(['room_id' => $room->id]);
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);

        expect($player->isReadyForRound($round))->toBeFalse();

        ReadyConfirmation::create([
            'round_id' => $round->id,
            'room_player_id' => $player->id,
            'confirmed_at' => now(),
        ]);

        expect($player->fresh()->isReadyForRound($round))->toBeTrue();
    });
});
